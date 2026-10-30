const loginForm = document.getElementById('loginForm');

const mensagemErro = document.getElementById('mensagemErro');
const campoEmail = document.getElementById('email');
const campoSenha = document.getElementById('password');
const mostrarSenha = document.getElementById('mostrarSenha');


// MOSTRAR / OCULTAR SENHA

mostrarSenha.addEventListener('change', function() {
    campoSenha.type = mostrarSenha.checked ? 'text' : 'password';
});


// MENSAGEM DE ERRO

function mostrarErro(texto, destacarCampos) {
    mensagemErro.textContent = texto;

    if (destacarCampos) {
        campoEmail.classList.add('erro');
        campoSenha.classList.add('erro');
    }
}

function limparErro() {
    mensagemErro.textContent = '';
    campoEmail.classList.remove('erro');
    campoSenha.classList.remove('erro');
}

// o erro some assim que o usuário volta a digitar
campoEmail.addEventListener('input', limparErro);
campoSenha.addEventListener('input', limparErro);


// LOGIN


loginForm.addEventListener('submit', async function(event) {

    event.preventDefault();


    limparErro();

    const email = campoEmail.value;
    const password = campoSenha.value;


    const url = "/SIMPUAEPA/login";

    const data = {
        email: email,
        password: password
    };

    try {

        const response = await fetch(url, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(data)
        });

        console.log("HTTP:", response.status);

        // 500: erro no banco (pode vir sem JSON)
        if (response.status === 500) {
            mostrarErro("Erro no servidor. Tente novamente mais tarde.", false);
            return;
        }

        const result = await response.json();
        console.log("Resposta:", result);

        // 200: success 0 = logou agora, success 1 = já estava logado
        if (response.ok) {
            localStorage.setItem('uid', result.uid);
            window.location.href = "../index.html";
            return;
        }

        // 400: e-mail ou senha com problema
        if (response.status === 400) {
            mostrarErro("E-mail ou senha inválidos.", true);
            return;
        }

        mostrarErro("Ocorreu um erro inesperado. Tente novamente.", false);

    } catch (error) {
        console.error("Erro na requisição:", error);
        mostrarErro("Não foi possível conectar ao servidor.", false);

    }

});


// DARK MODE

const themeToggle = document.getElementById('themeToggle');
const themeIcon = document.getElementById('themeIcon');

themeToggle.addEventListener('click', function() {

    document.body.classList.toggle('dark-mode');

    if (document.body.classList.contains('dark-mode')) {
        themeIcon.src = "../images/dia-e-noite-white.png";
    } else {
        themeIcon.src = "../images/dia-e-noite.png";
    }

});