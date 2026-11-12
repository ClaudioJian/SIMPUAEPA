

const BASE = "/SIMPUAEPA";

const API = {
    perfil: {
        url: BASE + "/user/profile?name=1&email=1&image_url=1"   
    },
    enviarFoto: {
        url: BASE + "/image/profile",   
        method: "POST",
        campoArquivo: "userfile"        
    },
    pastaFotos: BASE + "/image/profile/",   
    alterarEmail: {                        // PROVISÓRIO
        url: BASE + "/account/email",
        method: "POST",
        corpo: function (novoEmail, senhaAtual) {
            return { email: novoEmail, password: senhaAtual };
        }
    },
    alterarSenha: {                        // PROVISÓRIO
        url: BASE + "/account/password",
        method: "POST",
        corpo: function (senhaAtual, novaSenha) {
            return { password: senhaAtual, new_password: novaSenha };
        }
    },
    excluirConta: {                        // PROVISÓRIO
        url: BASE + "/account/delete",
        method: "POST",
        corpo: function (senha) {
            return { password: senha };
        }
    }
};

// O back-end exige no mínimo 1000ms entre requisições do mesmo IP (responde 429).
// Todas as esperas/tentativas abaixo usam este valor.
const INTERVALO_MS = 1200;
const MAX_TENTATIVAS = 4;

// Textos dos códigos de erro do HTTP 400 (PROVISÓRIOS)
const MENSAGENS_400 = {
    email:    { 1: "E-mail inválido.", 2: "Esse e-mail já está cadastrado.", 3: "Informe o e-mail." },
    password: { 1: "Senha fraca.", 2: "Senha atual incorreta.", 3: "Informe a senha." }
};


const FOTO = {
    tamanhoFinal: 512,
    tamanhoMaxBytes: 1024 * 1024
};

// Códigos de erro do upload (documentação 5.6.3)
const ERROS_UPLOAD = {
    "-1": "Extensão de arquivo inválida ou não permitida.",
    "-2": "Requisição inválida ou arquivo enviado de forma incompleta.",
    "-3": "O servidor não conseguiu iniciar a validação (problema interno).",
    "-4": "Muitas requisições. Aguarde um instante e tente de novo.",
    "-5": "O processamento excedeu o limite permitido.",
    "-6": "Falha interna ao processar a imagem.",
    "-7": "Esse formato de imagem não é suportado pelo servidor.",
    "-8": "A imagem excede o tamanho máximo permitido.",
    "-9": "O servidor não recebeu nenhum arquivo.",
    "-10": "Erro no banco de dados do servidor.",
    "-99": "Erro inesperado no servidor."
};

const TEXTO_ETAPAS = {
    0: "Enviando a foto...",
    1: "Imagem validada, processando...",
    2: "Verificação concluída, salvando...",
    3: "Foto salva."
};


// ================= FUNÇÕES AUXILIARES =================

function mostrarMsg(elemento, texto, tipo) {
    elemento.textContent = texto;
    elemento.className = 'msg ' + tipo;   // tipo: 'erro' ou 'ok'
}

function limparMsg(elemento) {
    elemento.textContent = '';
    elemento.className = 'msg';
}

// envia a requisição em JSON e devolve o status e o JSON (se houver)
async function enviar(config, corpo) {
    const response = await fetch(config.url, {
        method: config.method,
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify(corpo)
    });

    let result = null;
    try {
        result = await response.json();
    } catch (e) {
        // resposta sem JSON 
    }

    console.log("HTTP:", response.status);
    console.log("Resposta:", result);

    return { response: response, result: result };
}

// mensagem para quando a requisição não deu certo
function tratarFalha(status, result, elementoMsg) {

    // sessão expirada ou usuário não logado no servidor
    if (status === 401) {
        localStorage.removeItem('uid');
        window.location.href = "login.html";
        return;
    }

    // endpoint ainda não existe no back-end
    if (status === 404) {
        mostrarMsg(elementoMsg, "Esta função ainda não está disponível no servidor.", "erro");
        return;
    }

    // limite de requisições do back-end 
    if (status === 429) {
        mostrarMsg(elementoMsg, "Muitas requisições. Aguarde um instante e tente de novo.", "erro");
        return;
    }

    if (status === 500) {
        mostrarMsg(elementoMsg, "Erro no servidor. Tente novamente mais tarde.", "erro");
        return;
    }

    if (status === 400 && result) {
        const textos = [
            MENSAGENS_400.email[result.email],
            MENSAGENS_400.password[result.password]
        ].filter(Boolean);

        mostrarMsg(
            elementoMsg,
            textos.length ? textos.join(" ") : "Dados inválidos. Confira e tente novamente.",
            "erro"
        );
        return;
    }

    mostrarMsg(elementoMsg, "Ocorreu um erro inesperado. Tente novamente.", "erro");
}


// ================= DADOS ATUAIS DO USUÁRIO (documentação 5.2) =================

const fotoPadrao = document.getElementById('fotoPadrao');
const fotoAtual = document.getElementById('fotoAtual');
const perfilNome = document.getElementById('perfilNome');
const perfilEmail = document.getElementById('perfilEmail');
const emailAtual = document.getElementById('emailAtual');


function montarUrlFoto(valor) {
    if (!valor || !String(valor).trim()) { return null; }
    valor = String(valor).trim();

    // aponta só para a pasta, sem nome de arquivo: não é uma foto
    if (valor.endsWith("/")) { return null; }

    if (/^(https?:)?\/\//.test(valor) || valor.startsWith("/")) { return valor; }
    return "/SIMPUAEPA/image/profile/" + valor;
}

function mostrarFotoAtual(valor, forcarRecarga) {
    const base = montarUrlFoto(valor);

    if (!base) {
        fotoAtual.onload = null;
        fotoAtual.onerror = null;
        fotoAtual.hidden = true;
        fotoAtual.removeAttribute("src");
        fotoPadrao.style.display = "";
        return;
    }

    let tentativa = 0;

    function carregar() {
       
        const precisaBuster = forcarRecarga || tentativa > 0;

        fotoAtual.src = precisaBuster
            ? base + (base.indexOf("?") === -1 ? "?" : "&") + "t=" + Date.now()
            : base;
    }

    fotoAtual.onload = function () {
        fotoAtual.hidden = false;
        fotoPadrao.style.display = "none";
    };

    fotoAtual.onerror = function () {
        console.error("Falha ao carregar a foto:", fotoAtual.src);
        if (tentativa < 3) {
            tentativa++;
            setTimeout(carregar, INTERVALO_MS);
            return;
        }
        fotoAtual.hidden = true;
        fotoPadrao.style.display = "";
    };

    carregar();
}

function preencherPerfil(perfil, forcarRecarga) {
    perfilNome.textContent = perfil.name || "(sem nome)";
    perfilEmail.textContent = perfil.email || "";
    emailAtual.textContent = perfil.email || "-";
    mostrarFotoAtual(perfil.image_url, forcarRecarga);
}


async function carregarPerfil(forcarRecarga, tentativa) {
    tentativa = tentativa || 1;

    try {
        const response = await fetch(API.perfil.url, {
            credentials: "same-origin",
            cache: "no-store"
        });

        // 401: ninguém logado, volta para o login
        if (response.status === 401) {
            localStorage.removeItem('uid');
            window.location.href = "login.html";
            return;
        }

        if (response.status === 429 && tentativa < MAX_TENTATIVAS) {
            setTimeout(function () {
                carregarPerfil(forcarRecarga, tentativa + 1);
            }, INTERVALO_MS);
            return;
        }

        if (response.ok) {
            const perfil = await response.json();
            console.log("Perfil:", perfil);
            preencherPerfil(perfil, forcarRecarga);
            return;
        }

        console.error("Perfil: servidor respondeu", response.status);
        perfilNome.textContent = response.status === 429
            ? "Muitas requisições. Aguarde um instante e recarregue a página."
            : "Não foi possível carregar seus dados.";

    } catch (error) {
        console.error("Falha ao carregar o perfil:", error);
        perfilNome.textContent = "Não foi possível carregar seus dados.";
    }
}


// ================= MOSTRAR / OCULTAR SENHA =================
// cada checkbox controla os campos de senha do próprio formulário

document.querySelectorAll('.toggle-senha').forEach(function (caixa) {
    caixa.addEventListener('change', function () {
        const form = caixa.closest('form');

        form.querySelectorAll('input[data-senha]').forEach(function (campo) {
            campo.type = caixa.checked ? 'text' : 'password';
        });
    });
});

// a mensagem some assim que o usuário volta a digitar
document.querySelectorAll('form').forEach(function (form) {
    form.querySelectorAll('input:not([type="checkbox"])').forEach(function (campo) {
        campo.addEventListener('input', function () {
            limparMsg(form.querySelector('.msg'));
        });
    });
});


// ================= RESPOSTAS  UPLOAD =================

function sucessoDe(msg) {
    return msg.success !== undefined ? msg.success : msg.sucess;
}


function extrairJSONs(texto) {
    const objetos = [];
    let profundidade = 0;
    let inicio = -1;
    let consumido = 0;
    let emString = false;
    let escape = false;

    for (let i = 0; i < texto.length; i++) {
        const c = texto[i];

        if (emString) {
            if (escape) { escape = false; }
            else if (c === "\\") { escape = true; }
            else if (c === '"') { emString = false; }
            continue;
        }

        if (c === '"') {
            emString = true;
            continue;
        }

        if (c === "{") {
            if (profundidade === 0) { inicio = i; }
            profundidade++;
        } else if (c === "}") {
            profundidade--;
            if (profundidade === 0 && inicio !== -1) {
                objetos.push(texto.slice(inicio, i + 1));
                consumido = i + 1;
                inicio = -1;
            }
        }
    }

    return { objetos: objetos, resto: texto.slice(consumido) };
}


async function lerRespostasProgressivas(response, aoReceber) {
    const mensagens = [];
    let buffer = "";

    function processar() {
        const extraido = extrairJSONs(buffer);
        buffer = extraido.resto;

        extraido.objetos.forEach(function (texto) {
            try {
                const msg = JSON.parse(texto);
                console.log("Upload:", msg);
                mensagens.push(msg);
                aoReceber(msg);
            } catch (e) {
                console.warn("JSON inválido no upload:", texto);
            }
        });
    }

    
    if (!response.body || !response.body.getReader) {
        buffer = await response.text();
        processar();
        return mensagens;
    }

    const leitor = response.body.getReader();
    const decodificador = new TextDecoder();

    while (true) {
        const parte = await leitor.read();
        if (parte.done) { break; }
        buffer += decodificador.decode(parte.value, { stream: true });
        processar();
    }

    buffer += decodificador.decode();
    processar();

    return mensagens;
}


// ================= FOTO DE PERFIL  =================

const arquivoFoto = document.getElementById('arquivoFoto');
const btnEscolherFoto = document.getElementById('btnEscolherFoto');
const editorFoto = document.getElementById('editorFoto');
const canvasRecorte = document.getElementById('canvasRecorte');
const zoomFoto = document.getElementById('zoomFoto');
const btnSalvarFoto = document.getElementById('btnSalvarFoto');
const btnCancelarFoto = document.getElementById('btnCancelarFoto');
const msgFoto = document.getElementById('msgFoto');

const ctxRecorte = canvasRecorte.getContext('2d');
const V = canvasRecorte.width;   

// estado do recorte
let imagem = null;       // imagem escolhida
let escala = 1;          // tamanho atual da imagem em relação ao original
let escalaMinima = 1;    // menor escala possível
let deslocX = 0;         // posição da imagem dentro da área de recorte
let deslocY = 0;

function desenharRecorte() {
    ctxRecorte.fillStyle = "#fff";
    ctxRecorte.fillRect(0, 0, V, V);
    ctxRecorte.drawImage(imagem, deslocX, deslocY, imagem.width * escala, imagem.height * escala);
}


function limitarDeslocamento() {
    const w = imagem.width * escala;
    const h = imagem.height * escala;

    deslocX = Math.min(0, Math.max(V - w, deslocX));
    deslocY = Math.min(0, Math.max(V - h, deslocY));
}

function fecharEditorFoto() {
    editorFoto.hidden = true;
    btnEscolherFoto.hidden = false;
    imagem = null;
    arquivoFoto.value = '';   
}

btnEscolherFoto.addEventListener('click', function () {
    limparMsg(msgFoto);
    arquivoFoto.click();
});

arquivoFoto.addEventListener('change', function () {

    limparMsg(msgFoto);

    const arquivo = arquivoFoto.files[0];
    if (!arquivo) {
        return;
    }

    if (!arquivo.type.startsWith('image/')) {
        mostrarMsg(msgFoto, "Escolha um arquivo de imagem.", "erro");
        arquivoFoto.value = '';
        return;
    }

    const endereco = URL.createObjectURL(arquivo);
    const nova = new Image();

    nova.onload = function () {
        URL.revokeObjectURL(endereco);

        imagem = nova;
        escalaMinima = Math.max(V / nova.width, V / nova.height);
        escala = escalaMinima;
        deslocX = (V - nova.width * escala) / 2;   
        deslocY = (V - nova.height * escala) / 2;
        zoomFoto.value = 1;

        desenharRecorte();

        btnEscolherFoto.hidden = true;
        editorFoto.hidden = false;
    };

    nova.onerror = function () {
        URL.revokeObjectURL(endereco);
        mostrarMsg(msgFoto, "Não foi possível abrir essa imagem. Tente outra (JPG, PNG ou WebP).", "erro");
        arquivoFoto.value = '';
    };

    nova.src = endereco;
});



zoomFoto.addEventListener('input', function () {
    if (!imagem) {
        return;
    }

    const centroX = (V / 2 - deslocX) / escala;
    const centroY = (V / 2 - deslocY) / escala;

    escala = escalaMinima * parseFloat(zoomFoto.value);
    deslocX = V / 2 - centroX * escala;
    deslocY = V / 2 - centroY * escala;

    limitarDeslocamento();
    desenharRecorte();
});


let arrastando = false;
let ultimoX = 0;
let ultimoY = 0;

canvasRecorte.addEventListener('pointerdown', function (e) {
    if (!imagem) {
        return;
    }
    arrastando = true;
    ultimoX = e.clientX;
    ultimoY = e.clientY;
    canvasRecorte.setPointerCapture(e.pointerId);
});

canvasRecorte.addEventListener('pointermove', function (e) {
    if (!arrastando || !imagem) {
        return;
    }

    
    const proporcao = V / canvasRecorte.getBoundingClientRect().width;

    deslocX += (e.clientX - ultimoX) * proporcao;
    deslocY += (e.clientY - ultimoY) * proporcao;
    ultimoX = e.clientX;
    ultimoY = e.clientY;

    limitarDeslocamento();
    desenharRecorte();
});

function pararArrasto() {
    arrastando = false;
}

canvasRecorte.addEventListener('pointerup', pararArrasto);
canvasRecorte.addEventListener('pointercancel', pararArrasto);

btnCancelarFoto.addEventListener('click', function () {
    limparMsg(msgFoto);
    fecharEditorFoto();
});


function gerarImagemFinal(qualidade) {
    const saida = document.createElement('canvas');
    saida.width = FOTO.tamanhoFinal;
    saida.height = FOTO.tamanhoFinal;

    const ctx = saida.getContext('2d');
    const f = FOTO.tamanhoFinal / V;

    ctx.fillStyle = "#fff";   
    ctx.fillRect(0, 0, saida.width, saida.height);
    ctx.drawImage(imagem, deslocX * f, deslocY * f, imagem.width * escala * f, imagem.height * escala * f);

    return new Promise(function (resolve) {
        saida.toBlob(resolve, 'image/jpeg', qualidade);
    });
}

btnSalvarFoto.addEventListener('click', async function () {

    if (!imagem) {
        return;
    }

    limparMsg(msgFoto);
    btnSalvarFoto.disabled = true;

    try {
        
        let arquivo = null;
        const qualidades = [0.9, 0.8, 0.7, 0.6, 0.5];

        for (let i = 0; i < qualidades.length; i++) {
            arquivo = await gerarImagemFinal(qualidades[i]);
            if (arquivo && arquivo.size <= FOTO.tamanhoMaxBytes) {
                break;
            }
        }

        if (!arquivo || arquivo.size > FOTO.tamanhoMaxBytes) {
            mostrarMsg(msgFoto, "A imagem ficou maior que o limite de 1MB. Tente outra foto.", "erro");
            return;
        }

        
        const dados = new FormData();
        dados.append(API.enviarFoto.campoArquivo, arquivo, "perfil.jpg");
        dados.append("x", 0);
        dados.append("y", 0);
        dados.append("width", FOTO.tamanhoFinal);
        dados.append("height", FOTO.tamanhoFinal);

        mostrarMsg(msgFoto, TEXTO_ETAPAS[0], "ok");

        
        const response = await fetch(API.enviarFoto.url, {
            method: API.enviarFoto.method,
            credentials: "same-origin",
            body: dados
        });

        console.log("HTTP:", response.status);

        // falhas "de verdade" do HTTP (sessão, endpoint, servidor, limite de requisições)
        if (response.status === 401 || response.status === 404 ||
            response.status === 429 || response.status >= 500) {
            tratarFalha(response.status, null, msgFoto);
            return;
        }

        
        const mensagens = await lerRespostasProgressivas(response, function (msg) {
            if (sucessoDe(msg) === 0 && TEXTO_ETAPAS[msg.stage]) {
                mostrarMsg(msgFoto, TEXTO_ETAPAS[msg.stage], "ok");
            }
        });

        const falha = mensagens.find(function (m) { return sucessoDe(m) === -1; });
        const confirmou = mensagens.some(function (m) {
            return sucessoDe(m) === 0 && m.stage === 3;
        });

        if (falha) {
            mostrarMsg(msgFoto, ERROS_UPLOAD[String(falha.code)] || "O servidor recusou a imagem.", "erro");
            return;
        }

        
        if (confirmou) {
            fecharEditorFoto();
            mostrarMsg(msgFoto, "Foto atualizada com sucesso.", "ok");

            
            setTimeout(function () { carregarPerfil(true); }, INTERVALO_MS);
            return;
        }

        if (response.status === 400) {
            mostrarMsg(msgFoto, "O servidor não aceitou essa imagem. Tente outra.", "erro");
            return;
        }

        mostrarMsg(msgFoto, "O servidor não confirmou que a foto foi salva.", "erro");

    } catch (error) {
        console.error("Erro na requisição:", error);
        mostrarMsg(msgFoto, "Não foi possível conectar ao servidor.", "erro");
    } finally {
        btnSalvarFoto.disabled = false;
    }
});


// ================= ALTERAR E-MAIL =================

const formEmail = document.getElementById('formEmail');
const campoNovoEmail = document.getElementById('novoEmail');
const campoSenhaEmail = document.getElementById('senhaEmail');
const msgEmail = document.getElementById('msgEmail');

formEmail.addEventListener('submit', async function (event) {

    event.preventDefault();
    limparMsg(msgEmail);

    const novoEmail = campoNovoEmail.value.trim();
    const senha = campoSenhaEmail.value;

    if (novoEmail === '') {
        mostrarMsg(msgEmail, "Informe o novo e-mail.", "erro");
        return;
    }

    if (campoNovoEmail.validity.typeMismatch) {
        mostrarMsg(msgEmail, "E-mail inválido.", "erro");
        return;
    }

    if (senha === '') {
        mostrarMsg(msgEmail, "Informe sua senha atual para confirmar.", "erro");
        return;
    }

    try {
        const { response, result } = await enviar(API.alterarEmail, API.alterarEmail.corpo(novoEmail, senha));

        if (response.ok) {
            mostrarMsg(msgEmail, "E-mail alterado com sucesso.", "ok");
            campoNovoEmail.value = '';
            campoSenhaEmail.value = '';
            setTimeout(function () { carregarPerfil(); }, INTERVALO_MS);   // atualiza o e-mail mostrado na tela
        } else {
            tratarFalha(response.status, result, msgEmail);
        }

    } catch (error) {
        console.error("Erro na requisição:", error);
        mostrarMsg(msgEmail, "Não foi possível conectar ao servidor.", "erro");
    }
});


// ================= ALTERAR SENHA =================

const formSenha = document.getElementById('formSenha');
const campoSenhaAtual = document.getElementById('senhaAtual');
const campoNovaSenha = document.getElementById('novaSenha');
const campoConfirmarNova = document.getElementById('confirmarNovaSenha');
const msgSenha = document.getElementById('msgSenha');

formSenha.addEventListener('submit', async function (event) {

    event.preventDefault();
    limparMsg(msgSenha);

    const senhaAtual = campoSenhaAtual.value;
    const novaSenha = campoNovaSenha.value;
    const confirmar = campoConfirmarNova.value;

    if (senhaAtual === '') {
        mostrarMsg(msgSenha, "Informe sua senha atual.", "erro");
        return;
    }

    //  regra do cadastro: mais de 15 caracteres
    if (novaSenha.length <= 15) {
        mostrarMsg(msgSenha, "A nova senha deve ter mais de 15 caracteres.", "erro");
        return;
    }

    if (novaSenha !== confirmar) {
        mostrarMsg(msgSenha, "As senhas não coincidem.", "erro");
        return;
    }

    try {
        const { response, result } = await enviar(API.alterarSenha, API.alterarSenha.corpo(senhaAtual, novaSenha));

        if (response.ok) {
            mostrarMsg(msgSenha, "Senha alterada com sucesso.", "ok");
            campoSenhaAtual.value = '';
            campoNovaSenha.value = '';
            campoConfirmarNova.value = '';
        } else {
            tratarFalha(response.status, result, msgSenha);
        }

    } catch (error) {
        console.error("Erro na requisição:", error);
        mostrarMsg(msgSenha, "Não foi possível conectar ao servidor.", "erro");
    }
});


// ================= EXCLUIR CONTA =================

const btnAbrirExcluir = document.getElementById('btnAbrirExcluir');
const btnCancelarExcluir = document.getElementById('btnCancelarExcluir');
const formExcluir = document.getElementById('formExcluir');
const campoSenhaExcluir = document.getElementById('senhaExcluir');
const msgExcluir = document.getElementById('msgExcluir');

// primeiro clique: só mostra a confirmação 
btnAbrirExcluir.addEventListener('click', function () {
    btnAbrirExcluir.hidden = true;
    formExcluir.hidden = false;
    campoSenhaExcluir.focus();
});

btnCancelarExcluir.addEventListener('click', function () {
    formExcluir.hidden = true;
    btnAbrirExcluir.hidden = false;
    campoSenhaExcluir.value = '';
    limparMsg(msgExcluir);
});

formExcluir.addEventListener('submit', async function (event) {

    event.preventDefault();
    limparMsg(msgExcluir);

    const senha = campoSenhaExcluir.value;

    if (senha === '') {
        mostrarMsg(msgExcluir, "Digite sua senha para confirmar a exclusão.", "erro");
        return;
    }

    try {
        const { response, result } = await enviar(API.excluirConta, API.excluirConta.corpo(senha));

        if (response.ok) {
            localStorage.removeItem('uid');
            window.location.href = "../index.html";
        } else {
            tratarFalha(response.status, result, msgExcluir);
        }

    } catch (error) {
        console.error("Erro na requisição:", error);
        mostrarMsg(msgExcluir, "Não foi possível conectar ao servidor.", "erro");
    }
});


// ================= DARK MODE =================

const themeToggle = document.getElementById('themeToggle');
const themeIcon = document.getElementById('themeIcon');

themeToggle.addEventListener('click', function () {
    document.body.classList.toggle('dark-mode');

    if (document.body.classList.contains('dark-mode')) {
        themeIcon.src = "../images/dia-e-noite-white.png";
    } else {
        themeIcon.src = "../images/dia-e-noite.png";
    }
});


// ================= INÍCIO =================

carregarPerfil();