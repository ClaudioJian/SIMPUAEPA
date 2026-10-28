// ================= CONFIGURAÇÃO DO BACK-END =================
// perfil e enviarFoto vêm da documentação (5.2 e 5.7).
// Os itens marcados como PROVISÓRIOS ainda não existem na documentação:
// quando ela sair, ajuste só este bloco.

const API = {
    perfil: {
        url: "/SIMPUAEPA/user/profile?name=1&email=1&image_url=1"   // 5.2
    },
    enviarFoto: {
        url: "/SIMPUAEPA/image/profile",   // 5.7
        method: "POST",
        campoArquivo: "file"               // PROVISÓRIO: nome do campo do arquivo (seção 3.6)
    },
    alterarEmail: {                        // PROVISÓRIO
        url: "/SIMPUAEPA/account/email",
        method: "POST",
        corpo: function (novoEmail, senhaAtual) {
            return { email: novoEmail, password: senhaAtual };
        }
    },
    alterarSenha: {                        // PROVISÓRIO
        url: "/SIMPUAEPA/account/password",
        method: "POST",
        corpo: function (senhaAtual, novaSenha) {
            return { password: senhaAtual, new_password: novaSenha };
        }
    },
    excluirConta: {                        // PROVISÓRIO
        url: "/SIMPUAEPA/account/delete",
        method: "POST",
        corpo: function (senha) {
            return { password: senha };
        }
    }
};

// Textos dos códigos de erro do HTTP 400 (PROVISÓRIOS)
const MENSAGENS_400 = {
    email:    { 1: "E-mail inválido.", 2: "Esse e-mail já está cadastrado.", 3: "Informe o e-mail." },
    password: { 1: "Senha fraca.", 2: "Senha atual incorreta.", 3: "Informe a senha." }
};

// Limites da imagem de perfil (documentação 5.7: padrão 512x512 e 1MB).
// Se o servidor estiver configurado com outros valores, ajuste aqui.
const FOTO = {
    tamanhoFinal: 512,
    tamanhoMaxBytes: 1024 * 1024
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
        // resposta sem JSON (comum em 500 ou 404)
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

// forcarRecarga: depois de trocar a foto, evita que o navegador mostre a antiga do cache
function mostrarFotoAtual(url, forcarRecarga) {
    if (url) {
        fotoAtual.onerror = function () {
            fotoAtual.hidden = true;
            fotoPadrao.style.display = "";
        };

        fotoAtual.src = forcarRecarga
            ? url + (url.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now()
            : url;

        fotoAtual.hidden = false;
        fotoPadrao.style.display = "none";
    } else {
        fotoAtual.hidden = true;
        fotoAtual.removeAttribute('src');
        fotoPadrao.style.display = "";
    }
}

function preencherPerfil(perfil, forcarRecarga) {
    perfilNome.textContent = perfil.name || "(sem nome)";
    perfilEmail.textContent = perfil.email || "";
    emailAtual.textContent = perfil.email || "-";
    mostrarFotoAtual(perfil.image_url, forcarRecarga);
}

// Pergunta ao servidor quem está logado e carrega os dados
async function carregarPerfil(forcarRecarga) {
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

        if (response.ok) {
            const perfil = await response.json();
            preencherPerfil(perfil, forcarRecarga);
            return;
        }

        console.error("Perfil: servidor respondeu", response.status);
        perfilNome.textContent = "Não foi possível carregar seus dados.";

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


// ================= FOTO DE PERFIL (documentação 5.7) =================

const arquivoFoto = document.getElementById('arquivoFoto');
const btnEscolherFoto = document.getElementById('btnEscolherFoto');
const editorFoto = document.getElementById('editorFoto');
const canvasRecorte = document.getElementById('canvasRecorte');
const zoomFoto = document.getElementById('zoomFoto');
const btnSalvarFoto = document.getElementById('btnSalvarFoto');
const btnCancelarFoto = document.getElementById('btnCancelarFoto');
const msgFoto = document.getElementById('msgFoto');

const ctxRecorte = canvasRecorte.getContext('2d');
const V = canvasRecorte.width;   // tamanho da área de recorte na tela (280)

// estado do recorte
let imagem = null;       // imagem escolhida
let escala = 1;          // tamanho atual da imagem em relação ao original
let escalaMinima = 1;    // menor escala possível (a imagem sempre cobre a área toda)
let deslocX = 0;         // posição da imagem dentro da área de recorte
let deslocY = 0;

function desenharRecorte() {
    ctxRecorte.fillStyle = "#fff";
    ctxRecorte.fillRect(0, 0, V, V);
    ctxRecorte.drawImage(imagem, deslocX, deslocY, imagem.width * escala, imagem.height * escala);
}

// impede de arrastar a imagem para fora, deixando espaço vazio
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
    arquivoFoto.value = '';   // permite escolher o mesmo arquivo de novo
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
        deslocX = (V - nova.width * escala) / 2;   // começa centralizada
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

// ampliar mantendo o centro da área de recorte
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

// arrastar a imagem (mouse e toque)
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

    // a área pode aparecer menor que 280px em telas estreitas
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

// gera a imagem final (quadrada) a partir do que está enquadrado na tela
function gerarImagemFinal(qualidade) {
    const saida = document.createElement('canvas');
    saida.width = FOTO.tamanhoFinal;
    saida.height = FOTO.tamanhoFinal;

    const ctx = saida.getContext('2d');
    const f = FOTO.tamanhoFinal / V;

    ctx.fillStyle = "#fff";   // JPEG não tem transparência
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
        // reduz a qualidade, se preciso, até caber no limite do servidor
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

        // a imagem já está recortada, então o recorte pedido pelo servidor é ela inteira
        const dados = new FormData();
        dados.append(API.enviarFoto.campoArquivo, arquivo, "perfil.jpg");
        dados.append("x", 0);
        dados.append("y", 0);
        dados.append("width", FOTO.tamanhoFinal);
        dados.append("height", FOTO.tamanhoFinal);

        // sem Content-Type manual: o navegador monta o multipart sozinho
        const response = await fetch(API.enviarFoto.url, {
            method: API.enviarFoto.method,
            credentials: "same-origin",
            body: dados
        });















                // lê como texto primeiro, para ver a resposta mesmo quando não é JSON
        const texto = await response.text();
        let result = null;
        try {
            result = JSON.parse(texto);
        } catch (e) {
            // resposta sem JSON
        }

        console.log("HTTP:", response.status);
        console.log("Resposta (texto):", texto);
        console.log("Resposta (JSON):", result);








        




        
        if (response.ok) {
            fecharEditorFoto();
            mostrarMsg(msgFoto, "Foto atualizada com sucesso.", "ok");
            carregarPerfil(true);   // mostra a foto nova
            return;
        }

        // o servidor recusou a imagem (a seção 3.6 define os códigos exatos)
        if (response.status === 400 || response.status === 413 || response.status === 415) {
            mostrarMsg(msgFoto, "O servidor não aceitou essa imagem (formato ou tamanho). Tente outra.", "erro");
            return;
        }

        tratarFalha(response.status, result, msgFoto);

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
            carregarPerfil();   // atualiza o e-mail mostrado na tela
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

    // mesma regra do cadastro: mais de 15 caracteres
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

// primeiro clique: só mostra a confirmação (nada é excluído ainda)
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