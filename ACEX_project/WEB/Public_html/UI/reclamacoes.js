import { lerObjetosJSON } from './fluxo-json.js';


// ================= CONFIGURAÇÃO =================
// Os valores marcados como PROVISÓRIO não estão claros na documentação:
// se algo não funcionar, o ajuste é aqui.

const API = {
    publicar: "/SIMPUAEPA/upload/complain",   // 5.10
    consultar: "/SIMPUAEPA/complain"          // 5.11
};

// Limites padrão da 5.10 (o servidor pode ter outros valores configurados)
const LIMITES = { titulo: 60, descricao: 500, referencia: 60 };

// PROVISÓRIO: o limite real da imagem é configurado no servidor
const FOTO = {
    tamanhoMaxBytes: 5 * 1024 * 1024,
    tipos: ["image/jpeg", "image/png", "image/webp"]
};









const CONSULTA = {
    zoomMinimo: 14,          // PROVISÓRIO: abaixo disso a área seria grande demais (erro -7)
    porPagina: 50,
    maxPaginas: 4,           // páginas buscadas por consulta
    esperaMs: 1200,          // espera depois de mexer no mapa, antes de consultar
    intervaloMinimoMs: 1100, // o servidor exige pelo menos 1000 ms entre requisições
    escalaCoordenadas: 1     // PROVISÓRIO: se o servidor devolver ang_x e ang_y como inteiros
                             // multiplicados (ex.: 1000000), coloque esse fator aqui
};











// Tipos da 5.10: número do servidor -> grupo/ícone do mapa e nome
const TIPOS = {
    1: { chave: "outro",        nome: "Geral" },
    2: { chave: "perigo",       nome: "Área perigosa" },
    3: { chave: "poluicao",     nome: "Poluição" },
    4: { chave: "sonora",       nome: "Poluição sonora" },
    5: { chave: "esgoto",       nome: "Esgoto" },
    6: { chave: "eletricidade", nome: "Problema elétrico" },
    7: { chave: "rua",          nome: "Problema na rua" },
    8: { chave: "outro",        nome: "Outro problema" }
};

// Opções do <select> do formulário -> número do servidor
const TIPO_POR_CHAVE = {
    perigo: 2,
    poluicao: 3,
    sonora: 4,
    esgoto: 5,
    eletricidade: 6,
    rua: 7,
    outro: 8
};

// 5.10.1.4: campo inválido
const CAMPOS_INVALIDOS = {
    1: "título",
    2: "descrição",
    3: "referência do local",
    4: "coordenada x",
    5: "coordenada y",
    6: "nível de intensidade"
};

// 5.6.3: códigos de erro do upload da imagem
const ERROS_UPLOAD = {
    "-1": "extensão inválida (use JPG, PNG ou WebP).",
    "-2": "requisição inválida.",
    "-3": "problema interno no servidor.",
    "-4": "muitas requisições em pouco tempo, aguarde um instante.",
    "-6": "falha ao processar a imagem no servidor.",
    "-7": "esse formato de imagem não é suportado pelo servidor.",
    "-8": "a imagem excede o tamanho máximo permitido.",
    "-9": "nenhum arquivo chegou ao servidor.",
    "-10": "erro no banco de dados do servidor.",
    "-99": "erro inesperado."
};


// ================= TEXTOS DE ERRO =================

function mensagemPorStatus(status) {
    switch (status) {
        case 401:
        case 403: return "Faça login para registrar uma reclamação.";
        case 413: return "Algum dos valores enviados é grande demais.";
        case 422: return "O conteúdo foi recusado por ser inadequado.";
        case 429: return "Muitas requisições em pouco tempo. Aguarde um instante e tente de novo.";
        case 500: return "Erro no servidor. Tente novamente mais tarde.";
        default:  return "O servidor respondeu de forma inesperada. Tente novamente.";
    }
}

// 5.10.1.5: err_code + invalid_field
function descreverErroPublicacao(obj, status) {
    const campo = CAMPOS_INVALIDOS[obj.invalid_field];

    switch (obj.err_code) {
        case -1: return campo ? `O campo "${campo}" está vazio.` : "Há um campo obrigatório vazio.";
        case -2: return "Os dados estão desatualizados. Recarregue a página.";
        case -3: return "Erro no servidor. Tente novamente mais tarde.";
        case -4:
        case -5: return "O conteúdo foi recusado pelas regras do sistema.";
        case -6: return "Você não tem permissão para registrar reclamações.";
        case -7: return "A localização escolhida é inválida. Clique no mapa e tente de novo.";
        case -8: return campo ? `O campo "${campo}" é grande demais.` : "Algum texto é grande demais.";
        case -9: return campo ? `O campo "${campo}" tem um valor inválido.` : "Algum valor é inválido.";
        case -99: return "Erro inesperado no servidor.";
        default: return mensagemPorStatus(status);
    }
}

function escaparHTML(texto) {
    return String(texto ?? "").replace(/[&<>"']/g, function (c) {
        return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
}

function formatarData(valor) {
    if (!valor) {
        return "";
    }

    const data = new Date(String(valor).replace(" ", "T"));
    return isNaN(data) ? String(valor) : data.toLocaleDateString("pt-BR");
}


// ================= INÍCIO =================

export function iniciarReclamacoes(ctx) {

    const L = ctx.L;
    const map = ctx.map;
    const grupos = ctx.grupos;
    const icones = ctx.icones;

    const formulario = document.getElementById("form_reclamacao");
    const campoStatus = document.getElementById("status_reclamacao");
    const campoFoto = document.getElementById("foto_reclamacao");
    const botaoEnviar = document.getElementById("enviar_reclamacao");


    // ---------- mensagens ----------

    // mensagem dentro do formulário (tipo: "erro", "ok" ou "progresso")
    function mostrarStatus(texto, tipo) {
        campoStatus.textContent = texto;
        campoStatus.className = tipo || "";
    }

    // aviso rápido na parte de baixo da tela (para a consulta, que não tem formulário)
    const toast = document.createElement("div");
    toast.className = "toast-mapa";
    toast.setAttribute("role", "status");
    document.body.appendChild(toast);

    let temporizadorToast = null;

    function mostrarToast(texto) {
        toast.textContent = texto;
        toast.classList.add("aberto");

        clearTimeout(temporizadorToast);
        temporizadorToast = setTimeout(function () {
            toast.classList.remove("aberto");
        }, 4000);
    }


    // ---------- marcadores das reclamações ----------

    const marcadores = new Map();   // complain_id -> marcador (evita duplicados)

    function criarPopup(rec) {
        const tipo = TIPOS[rec.type] || TIPOS[1];
        const data = formatarData(rec.created_at);

        return `
            <div class="popup-reclamacao">
                <h3>${escaparHTML(rec.title)}</h3>
                <p class="popup-tipo">${escaparHTML(tipo.nome)}</p>
                <p>${escaparHTML(rec.description)}</p>

                ${rec.location_reference ? `
                    <p><strong>Referência:</strong> ${escaparHTML(rec.location_reference)}</p>
                ` : ""}

                ${rec.image_url ? `
                    <img class="foto-reclamacao"
                         src="${escaparHTML(rec.image_url)}"
                         alt="Foto enviada pelo usuário"
                         onerror="this.style.display='none'">
                ` : ""}

                <p class="popup-rodape">
                    👍 ${Number(rec.likes) || 0} · 👎 ${Number(rec.dislikes) || 0}${data ? " · " + escaparHTML(data) : ""}
                </p>
            </div>
        `;
    }

    // opcoes.latlng: posição já conhecida (usada logo depois de publicar)
    function adicionarMarcador(rec, opcoes) {
        const id = String(rec.complain_id);

        if (marcadores.has(id)) {
            return marcadores.get(id);
        }

        let posicao;

        if (opcoes && opcoes.latlng) {
            posicao = opcoes.latlng;
        } else {
            const lat = Number(rec.ang_y) / CONSULTA.escalaCoordenadas;   // y = latitude
            const lng = Number(rec.ang_x) / CONSULTA.escalaCoordenadas;   // x = longitude

            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                console.warn("Reclamação sem coordenadas válidas:", rec);
                return null;
            }

            posicao = [lat, lng];
        }

        const tipo = TIPOS[rec.type] || TIPOS[1];

        const marcador = new L.Marker(posicao, {
            icon: icones[tipo.chave],
            draggable: false,
            interactive: true
        });

        marcador.bindPopup(criarPopup(rec));
        grupos[tipo.chave].addLayer(marcador);
        marcadores.set(id, marcador);

        return marcador;
    }


   






        // ---------- consulta (documentação 5.11) ----------

    // O servidor recusa (429) requisições com menos de 1000 ms de intervalo entre si.
    // Por isso cada pedido da consulta (e a publicação) espera aqui antes de sair.
    let ultimaRequisicao = Date.now();   // a página acabou de carregar (o perfil já foi pedido)

    async function esperarIntervalo(sinal) {
        const falta = ultimaRequisicao + CONSULTA.intervaloMinimoMs - Date.now();

        if (falta > 0) {
            await new Promise(function (resolver) {
                setTimeout(resolver, falta);
            });
        }

        // a consulta foi cancelada enquanto esperava (o usuário moveu o mapa)
        if (sinal && sinal.aborted) {
            return false;
        }

        ultimaRequisicao = Date.now();
        return true;
    }

    let controladorConsulta = null;
    let temporizadorConsulta = null;
    let bloqueadoAte = 0;
    let falhas429 = 0;

    function agendarConsulta() {
        clearTimeout(temporizadorConsulta);

        const espera = Math.max(CONSULTA.esperaMs, bloqueadoAte - Date.now());

        temporizadorConsulta = setTimeout(function () {
            consultarReclamacoes(false);
        }, espera);
    }

    async function consultarReclamacoes(reiniciada) {

        if (map.getZoom() < CONSULTA.zoomMinimo) {
            mostrarToast("Aproxime o mapa para ver as reclamações desta região.");
            return;
        }

        // cancela a consulta anterior, se ainda estiver em andamento
        if (controladorConsulta) {
            controladorConsulta.abort();
        }

        const controlador = new AbortController();
        controladorConsulta = controlador;

        const limites = map.getBounds();
        let proximo = "";

        try {
            for (let pagina = 0; pagina < CONSULTA.maxPaginas; pagina++) {

                // respeita o intervalo mínimo do servidor, inclusive entre uma página e outra
                if (!(await esperarIntervalo(controlador.signal))) {
                    return;
                }

                const parametros = new URLSearchParams();
                parametros.set("quant", String(CONSULTA.porPagina));
                parametros.set("ang_bbox[min_ang_x]", String(limites.getWest()));    // esquerda
                parametros.set("ang_bbox[min_ang_y]", String(limites.getSouth()));   // inferior
                parametros.set("ang_bbox[max_ang_x]", String(limites.getEast()));    // direita
                parametros.set("ang_bbox[max_ang_y]", String(limites.getNorth()));   // superior

                // o token é enviado exatamente como foi recebido
                if (proximo) {
                    parametros.set("next", proximo);
                }

                const resposta = await fetch(API.consultar + "?" + parametros.toString(), {
                    credentials: "same-origin",
                    cache: "no-store",
                    signal: controlador.signal
                });

                // 429: espera e tenta de novo, no máximo 3 vezes seguidas
                if (resposta.status === 429) {
                    falhas429++;

                    if (falhas429 > 3) {
                        falhas429 = 0;
                        mostrarToast("O servidor está limitando as consultas. Mova o mapa para tentar de novo.");
                        return;
                    }

                    const espera = Number(resposta.headers.get("Retry-After")) || 2;
                    bloqueadoAte = Date.now() + espera * 1000;
                    agendarConsulta();
                    return;
                }

                let resultado = null;
                try {
                    resultado = await resposta.json();
                } catch (e) {
                    // resposta sem JSON (ex.: 500)
                }

                // sucesso
                if (resposta.ok && resultado && resultado.success === 0) {

                    falhas429 = 0;

                    const lista = Array.isArray(resultado.attachment) ? resultado.attachment : [];
                    lista.forEach(function (rec) {
                        adicionarMarcador(rec);
                    });

                    // os resultados mudaram durante a consulta: recomeça uma vez
                    if (resultado.updated === 1 && !reiniciada) {
                        mostrarToast("As reclamações foram atualizadas. Recarregando a lista...");
                        return consultarReclamacoes(true);
                    }

                    // sem last_cursor (ou página vazia), não há mais reclamações
                    proximo = resultado.last_cursor || "";

                    if (!proximo || lista.length === 0) {
                        return;
                    }

                    continue;
                }

                // erros
                const codigo = resultado ? resultado.err_code : null;

                if (codigo === -2) {
                    return;   // local ainda sem reclamações: não é erro de verdade
                }

                if (codigo === -7 || resposta.status === 413) {
                    mostrarToast("Aproxime o mapa para ver as reclamações desta região.");
                    return;
                }

                console.error("Consulta de reclamações falhou:", resposta.status, resultado);

                if (resposta.status === 500) {
                    bloqueadoAte = Date.now() + 15000;   // não insiste por 15 segundos
                    mostrarToast("Erro no servidor ao buscar as reclamações.");
                }

                return;
            }

        } catch (erro) {
            if (erro.name !== "AbortError") {
                console.error("Erro ao consultar as reclamações:", erro);
            }
        }
    }

    map.on("moveend", agendarConsulta);
    agendarConsulta();




































    // ---------- publicação (documentação 5.10) ----------

    formulario.addEventListener("submit", async function (evento) {

        evento.preventDefault();
        mostrarStatus("", "");

        const marcadorProvisorio = ctx.obterMarcadorProvisorio();

        if (!marcadorProvisorio) {
            ctx.abrirAviso(
                "Localização não encontrada",
                "Clique no mapa para selecionar o local da reclamação."
            );
            return;
        }

        if (!localStorage.getItem("uid")) {
            mostrarStatus("Faça login para registrar uma reclamação.", "erro");
            return;
        }

        const dados = new FormData(formulario);
        const chaveTipo = String(dados.get("tipo_reclamacao") || "");
        const titulo = String(dados.get("titulo_reclamacao") || "").trim();
        const descricao = String(dados.get("descricao_reclamacao") || "").trim();
        const referencia = String(dados.get("referencia_local") || "").trim();
        const foto = campoFoto && campoFoto.files[0] ? campoFoto.files[0] : null;

        // validações antes de chamar o servidor
        if (titulo === "") {
            mostrarStatus("Informe o título.", "erro");
            return;
        }

        if (titulo.length > LIMITES.titulo) {
            mostrarStatus(`O título deve ter até ${LIMITES.titulo} caracteres.`, "erro");
            return;
        }

        if (descricao === "") {
            mostrarStatus("Informe a descrição do problema.", "erro");
            return;
        }

        if (descricao.length > LIMITES.descricao) {
            mostrarStatus(`A descrição deve ter até ${LIMITES.descricao} caracteres.`, "erro");
            return;
        }

        if (referencia.length > LIMITES.referencia) {
            mostrarStatus(`A referência deve ter até ${LIMITES.referencia} caracteres.`, "erro");
            return;
        }

        if (foto) {
            if (FOTO.tipos.indexOf(foto.type) === -1) {
                mostrarStatus("A foto deve ser JPG, PNG ou WebP.", "erro");
                return;
            }

            if (foto.size > FOTO.tamanhoMaxBytes) {
                mostrarStatus("A foto é grande demais. Escolha uma menor.", "erro");
                return;
            }
        }

        // coordenadas: x = longitude, y = latitude
        const posicao = marcadorProvisorio.getLatLng();
        const numeroTipo = TIPO_POR_CHAVE[chaveTipo] || 1;

        // PROVISÓRIO: envio em multipart/form-data (necessário para mandar o arquivo)
        const corpo = new FormData();
        corpo.append("title", titulo);
        corpo.append("description", descricao);

        if (referencia !== "") {
            corpo.append("location_reference", referencia);
        }

        corpo.append("type", String(numeroTipo));
        corpo.append("ang_x", String(posicao.lng));
        corpo.append("ang_y", String(posicao.lat));

        if (foto) {
            corpo.append("userfile", foto);
        }

        botaoEnviar.disabled = true;
        mostrarStatus(foto ? "Enviando a reclamação e a foto..." : "Enviando a reclamação...", "progresso");

        try {
            // sem Content-Type manual: o navegador monta o multipart sozinho

            await esperarIntervalo();

            const resposta = await fetch(API.publicar, {
                method: "POST",
                credentials: "same-origin",
                body: corpo
            });








            let primeira = null;       // resposta da etapa 1 (dados da reclamação)
            let ultima = null;         // última resposta com "complete" (traz o image_url)
            let falhaUpload = null;    // falha no processamento da foto
            let falhaFinal = null;     // falha na etapa final

            // a resposta vem em vários JSONs (5.10.2 e 5.6.3)
            const lidos = await lerObjetosJSON(resposta, function (obj) {
                console.log("Resposta do servidor:", obj);

                if (obj.stage !== undefined) {
                    // etapas do upload; o servidor escreve "sucess" nas etapas 1 a 3
                    const resultado = obj.success !== undefined ? obj.success : obj.sucess;

                    if (resultado === -1 && !falhaUpload) {
                        falhaUpload = obj;
                    }
                } else if (obj.complete !== undefined) {
                    if (!primeira) {
                        primeira = obj;
                    } else if (obj.complete === -1 && !falhaFinal) {
                        falhaFinal = obj;
                    }
                    ultima = obj;
                }
            });










            console.log("HTTP:", resposta.status, "| Resposta (texto):", lidos.texto);

            // nenhum JSON: só o código HTTP diz o que houve
            if (!primeira) {
                mostrarStatus(mensagemPorStatus(resposta.status), "erro");
                return;
            }

            if (primeira.complete === -1) {
                mostrarStatus(descreverErroPublicacao(primeira, resposta.status), "erro");
                return;
            }

            if (falhaFinal) {
                mostrarStatus(descreverErroPublicacao(falhaFinal, resposta.status), "erro");
                return;
            }

            if (falhaUpload) {
                mostrarStatus(
                    "Não foi possível enviar a foto: " + (ERROS_UPLOAD[falhaUpload.code] || "erro desconhecido."),
                    "erro"
                );
                return;
            }

















            const idServidor = (ultima && ultima.complain_id !== undefined) ? ultima.complain_id : primeira.complain_id;

            const registro = {
                complain_id: idServidor !== undefined ? idServidor : "local-" + Date.now(),
                title: titulo,
                description: descricao,
                location_reference: referencia,
                type: numeroTipo,
                image_url: (ultima && ultima.image_url) || primeira.image_url || "",
                likes: 0,
                dislikes: 0,
                created_at: new Date().toISOString()
            };
































            const marcador = adicionarMarcador(registro, { latlng: posicao });

            ctx.limparMarcadorProvisorio();

            if (marcador) {
                const grupo = grupos[(TIPOS[numeroTipo] || TIPOS[1]).chave];

                if (!map.hasLayer(grupo)) {
                    map.addLayer(grupo);   // garante que o novo marcador apareça
                }

                bloqueadoAte = Date.now() + 2000;
                marcador.openPopup();
            }

            const caixa = document.getElementById("caixa_reclamacao");
            if (caixa) {
                caixa.classList.add("visivel");   // nesse projeto, "visivel" esconde a caixa
            }

            formulario.reset();
            mostrarStatus("", "");

            ctx.abrirAviso("Reclamação enviada", "Sua reclamação foi registrada com sucesso.");

        } catch (erro) {
            console.error("Erro ao enviar a reclamação:", erro);
            mostrarStatus("Não foi possível conectar ao servidor.", "erro");
        } finally {
            botaoEnviar.disabled = false;
        }
    });
}