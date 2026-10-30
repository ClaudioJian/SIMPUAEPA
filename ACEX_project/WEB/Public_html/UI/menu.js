// ================= MENU LATERAL =================

        const hamburger = document.getElementById("hamburger");
        const menu = document.getElementById("menu");
        const overlay = document.getElementById("overlay");

        function abrirMenu() {
            menu.classList.add("open");
            overlay.classList.add("open");
            hamburger.classList.add("open");

            hamburger.setAttribute("aria-expanded", "true");
            hamburger.setAttribute("aria-label", "Fechar menu");
        }

        function fecharMenu() {
            menu.classList.remove("open");
            overlay.classList.remove("open");
            hamburger.classList.remove("open");

            hamburger.setAttribute("aria-expanded", "false");
            hamburger.setAttribute("aria-label", "Abrir menu");
        }

        hamburger.addEventListener("click", function () {
            if (menu.classList.contains("open")) {
                fecharMenu();
            } else {
                abrirMenu();
            }
        });

        overlay.addEventListener("click", fecharMenu);

        // Fecha o menu com a tecla Escape
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                fecharMenu();
                fecharDropdown();
            }
        });

        // Impede os cliques no menu de chegarem ao mapa
        //L.DomEvent.disableClickPropagation(menu);
        //L.DomEvent.disableScrollPropagation(menu);

        // Exemplo de ação dos itens do menu
        menu.querySelectorAll("li[data-acao]").forEach(function (item) {
            item.addEventListener("click", function () {
                const acao = item.dataset.acao;

                console.log("Item selecionado:", acao);

                // Implemente a ação desejada para cada opção.
                fecharMenu();
            });
        });


        


        // ================= LOGIN / AVATAR / LOGOUT =================

        const loginContainer = document.getElementById("login-container");
        const btnLogin = document.getElementById("btn-login");
        const userMenu = document.getElementById("user-menu");
        const avatar = document.getElementById("avatar");
        const avatarPadrao = document.getElementById("avatar-padrao");
        const avatarFoto = document.getElementById("avatar-foto");
        const dropdown = document.getElementById("dropdown");
        const userNome = document.getElementById("user-nome");
        const userEmail = document.getElementById("user-email");
        const btnLogout = document.getElementById("btn-logout");

        // Impede que cliques no login acionem o mapa
        //L.DomEvent.disableClickPropagation(loginContainer);

        // Mostra a foto do usuário; sem foto (ou se ela falhar), volta para a silhueta
        // image_url pode vir como URL completa, caminho absoluto ou só o nome do arquivo
       function montarUrlFoto(valor) {
            if (!valor || !String(valor).trim()) { return null; }
            valor = String(valor).trim();

            // aponta só para a pasta, sem nome de arquivo: não é uma foto
            if (valor.endsWith("/")) { return null; }

            if (/^(https?:)?\/\//.test(valor) || valor.startsWith("/")) { return valor; }
            return "/SIMPUAEPA/image/profile/" + valor;
        }

        // Mostra a foto do usuário; sem foto (ou se ela falhar), volta para a silhueta
        const INTERVALO_MS = 1200;   // o back-end exige no mínimo 1000ms entre requisições

        function mostrarFoto(valor) {
            
            const base = montarUrlFoto(valor);

            if (!base) {
                avatarFoto.onload = null;
                avatarFoto.onerror = null;
                avatarFoto.hidden = true;
                avatarFoto.removeAttribute("src");
                avatarPadrao.style.display = "";
                return;
            }

            let tentativa = 0;

            function carregar() {
                avatarFoto.src = tentativa > 0
                    ? base + (base.indexOf("?") === -1 ? "?" : "&") + "t=" + Date.now()
                    : base;
            }

            avatarFoto.onload = function () {
                avatarFoto.hidden = false;
                avatarPadrao.style.display = "none";
            };

            avatarFoto.onerror = function () {
                console.error("Falha ao carregar a foto:", avatarFoto.src);
                if (tentativa < 3) {
                    tentativa++;
                    setTimeout(carregar, INTERVALO_MS);
                    return;
                }
                avatarFoto.hidden = true;
                avatarPadrao.style.display = "";
            };

            // espera o intervalo mínimo desde a requisição do perfil
            setTimeout(carregar, INTERVALO_MS);
        }

        // perfil é opcional: sem ele, só troca o botão Login pela bolinha
        function mostrarLogado(perfil) {
            btnLogin.style.display = "none";
            userMenu.style.display = "block";

            if (perfil) {
                userNome.textContent = perfil.name || "";
                userEmail.textContent = perfil.email || "";
                mostrarFoto(perfil.image_url);
            }
        }

        function mostrarDeslogado() {
            userMenu.style.display = "none";
            dropdown.style.display = "none";
            avatar.setAttribute("aria-expanded", "false");
            btnLogin.style.display = "inline-block";

            userNome.textContent = "";
            userEmail.textContent = "";
            mostrarFoto(null);
        }

        // Pergunta ao servidor quem está logado (documentação 5.2)
        async function verificarLogin(tentativa) {
            tentativa = tentativa || 1;

            if (tentativa === 1) {
                if (localStorage.getItem("uid")) { mostrarLogado(); } else { mostrarDeslogado(); }
            }

            try {
                const resposta = await fetch(
                    "/SIMPUAEPA/user/profile?name=1&email=1&image_url=1",
                    { credentials: "same-origin", cache: "no-store" }
                );

                if (resposta.status === 401) {
                    localStorage.removeItem("uid");
                    mostrarDeslogado();
                    return;
                }

                if (resposta.status === 429 && tentativa < 4) {
                    setTimeout(function () { verificarLogin(tentativa + 1); }, 1200);
                    return;
                }

                if (resposta.ok) {
                    mostrarLogado(await resposta.json());
                    return;
                }

                console.error("Perfil: servidor respondeu", resposta.status);
            } catch (err) {
                console.error("Falha ao verificar o login:", err);
            }
        }

        function fecharDropdown() {
            dropdown.style.display = "none";
            avatar.setAttribute("aria-expanded", "false");
        }

        // Abre ou fecha o menu do usuário
        avatar.addEventListener("click", function (e) {
            e.stopPropagation();

            const estaAberto = dropdown.style.display === "block";

            dropdown.style.display = estaAberto ? "none" : "block";

            avatar.setAttribute("aria-expanded", String(!estaAberto));
        });

        // Fecha o dropdown ao clicar fora
        document.addEventListener("click", function (e) {
            if (!userMenu.contains(e.target)) {
                fecharDropdown();
            }
        });

        // Logout
        btnLogout.addEventListener("click", async function () {
            try {
                const resposta = await fetch("/SIMPUAEPA/logout", {
                    method: "POST",
                    credentials: "same-origin"
                });

                if (!resposta.ok) {
                    console.error(
                        "O servidor retornou um erro no logout:",
                        resposta.status
                    );
                }
            } catch (err) {
                console.error("Falha no logout:", err);
            }

            localStorage.removeItem("uid");
            mostrarDeslogado();
        });

        verificarLogin();