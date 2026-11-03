const registerForm = document.getElementById('registerForm');
const mostrarSenha = document.getElementById('mostrarSenha');
const erroGeral = document.getElementById('erroGeral');

// cada campo com o seu input e a sua mensagem de erro
const campos = {
    name:     { input: document.getElementById('name'),             erro: document.getElementById('erroName') },
    email:    { input: document.getElementById('email'),            erro: document.getElementById('erroEmail') },
    password: { input: document.getElementById('password'),         erro: document.getElementById('erroPassword') },
    confirm:  { input: document.getElementById('confirm_password'), erro: document.getElementById('erroConfirm') }
};


// MENSAGENS DE ERRO

function mostrarErroCampo(chave, texto) {
    campos[chave].erro.textContent = texto;
    campos[chave].input.classList.add('erro');
}

function limparErroCampo(chave) {
    campos[chave].erro.textContent = '';
    campos[chave].input.classList.remove('erro');
}

function mostrarErroGeral(texto) {
    erroGeral.textContent = texto;
}

function limparErros() {
    Object.keys(campos).forEach(limparErroCampo);
    erroGeral.textContent = '';
}

// o erro de um campo some assim que o usuário volta a digitar nele
Object.keys(campos).forEach(function(chave) {
    campos[chave].input.addEventListener('input', function() {
        limparErroCampo(chave);
        erroGeral.textContent = '';

        // mudar a senha também invalida o aviso de "senhas não coincidem"
        if (chave === 'password') {
            limparErroCampo('confirm');
        }
    });
});


// MOSTRAR / OCULTAR SENHA (os dois campos de uma vez)

mostrarSenha.addEventListener('change', function() {
    const tipo = mostrarSenha.checked ? 'text' : 'password';
    campos.password.input.type = tipo;
    campos.confirm.input.type = tipo;
});


// CADASTRO

registerForm.addEventListener('submit', async function(event) {

    event.preventDefault();

    limparErros();

    const name = campos.name.input.value;
    const email = campos.email.input.value;
    const password = campos.password.input.value;
    const confirm_password = campos.confirm.input.value;

    // validações no front, antes de chamar o servidor
    let temErro = false;

    // restrição da especificação: a senha deve ter mais de 15 caracteres
    if (password.length <= 15) {
        mostrarErroCampo('password', "A senha deve ter mais de 15 caracteres.");
        temErro = true;
    }

    if (password !== confirm_password) {
        mostrarErroCampo('confirm', "As senhas não coincidem.");
        temErro = true;
    }

    if (temErro) {
        return;
    }

    const url = "/SIMPUAEPA/subscribe";

    const data = {
        name: name,
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

        // 303: usuário já cadastrado, o destino vem no header
        if (response.status === 303) {
            const destino = response.headers.get('x-target-location');
            console.log("x-target-location:", destino);

            if (destino) {
                window.location.href = destino;
            } else {
                mostrarErroGeral("Você já possui uma conta cadastrada.");
            }
            return;
        }

        // 500: erro no banco (pode vir sem JSON)
        if (response.status === 500) {
            mostrarErroGeral("Erro no servidor. Tente novamente mais tarde.");
            return;
        }

        const result = await response.json();
        console.log("Resposta:", result);

        // 201: conta criada
        if (response.status === 201 && result.success === 0) {
            localStorage.setItem('uid', result.uid);
            window.location.href = "../index.html";
            return;
        }

        // 400: mostra o problema no campo correspondente
        if (response.status === 400) {
            const msgName = { 1: "Nome muito longo.", 2: "Esse nome já está cadastrado.", 3: "Informe o nome." };
            const msgEmail = { 1: "E-mail inválido.", 2: "Esse e-mail já está cadastrado.", 3: "Informe o e-mail." };
            const msgPassword = { 1: "Senha fraca.", 3: "Informe a senha." };

            let algumErro = false;

            if (msgName[result.name]) {
                mostrarErroCampo('name', msgName[result.name]);
                algumErro = true;
            }

            if (msgEmail[result.email]) {
                mostrarErroCampo('email', msgEmail[result.email]);
                algumErro = true;
            }

            if (msgPassword[result.password]) {
                mostrarErroCampo('password', msgPassword[result.password]);
                algumErro = true;
            }

            if (!algumErro) {
                mostrarErroGeral("Dados inválidos.");
            }
            return;
        }

        mostrarErroGeral("Ocorreu um erro inesperado. Tente novamente.");

    } catch (error) {
        console.error("Erro na requisição:", error);
        mostrarErroGeral("Não foi possível conectar ao servidor.");
    }

});


// DARK MODE ABAIXO:
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