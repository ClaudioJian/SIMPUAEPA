const registerForm = document.getElementById('registerForm');

registerForm.addEventListener('submit', async function(event) {

    event.preventDefault();

    const name = registerForm.elements.name.value;
    const email = registerForm.elements.email.value;
    const password = registerForm.elements.password.value;
    const confirm_password = document.getElementById('confirm_password').value;

    if (password !== confirm_password) {
        alert("As senhas não coincidem! Por favor, verifique.");
        return;
    }

    // restrição da especificação: a senha deve ter mais de 15 caracteres
    if (password.length <= 15) {
        alert("A senha deve ter mais de 15 caracteres.");
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
            }
            return;
        }

        // 500: erro no banco (pode vir sem JSON)
        if (response.status === 500) {
            alert("Erro no servidor. Tente novamente mais tarde.");
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

        // 400: mostra o que está errado em cada campo
        if (response.status === 400) {
            const msgName = { 1: "Nome muito longo.", 2: "Esse nome já está cadastrado.", 3: "Informe o nome." };
            const msgEmail = { 1: "E-mail inválido.", 2: "Esse e-mail já está cadastrado.", 3: "Informe o e-mail." };
            const msgPassword = { 1: "Senha fraca.", 3: "Informe a senha." };

            const erros = [
                msgName[result.name],
                msgEmail[result.email],
                msgPassword[result.password]
            ].filter(Boolean);

            alert(erros.length ? erros.join("\n") : "Dados inválidos.");
        }

    } catch (error) {
        console.error("Erro na requisição:", error);
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