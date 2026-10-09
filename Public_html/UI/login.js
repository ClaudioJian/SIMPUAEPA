const loginForm = document.getElementById('loginForm');

loginForm.addEventListener('submit', async function(event) {

    event.preventDefault();

    const email = loginForm.elements.email.value;
    const password = loginForm.elements.password.value;

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

        const result = await response.json();

        console.log("HTTP:", response.status);
        console.log("Resposta:", result);

        // HTTP 200: success 0 = logou agora, success 1 = já estava logado
        if (response.ok) {
            localStorage.setItem('uid', result.uid);
            window.location.href = "../index.html";
        }

    } catch (error) {
        console.error("Erro na requisição:", error);
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