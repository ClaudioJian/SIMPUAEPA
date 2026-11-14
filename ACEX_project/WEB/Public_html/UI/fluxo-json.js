// Lê uma resposta com vários objetos JSON em sequência (documentação 5.6.3),
// chamando aoReceber para cada objeto assim que ele fica completo.
// Funciona mesmo se o servidor mandar o JSON formatado em várias linhas
// ou se um objeto chegar dividido em pedaços.

function criarExtrator(aoObjeto) {
    let texto = "";
    let pos = 0;           // próximo caractere a analisar
    let inicio = -1;       // onde começou o objeto atual
    let profundidade = 0;
    let emTexto = false;
    let escapado = false;

    return function (pedaco) {
        texto += pedaco;

        for (; pos < texto.length; pos++) {
            const c = texto[pos];

            if (emTexto) {
                if (escapado) {
                    escapado = false;
                } else if (c === "\\") {
                    escapado = true;
                } else if (c === '"') {
                    emTexto = false;
                }
                continue;
            }

            if (c === '"') {
                emTexto = true;
            } else if (c === "{") {
                if (profundidade === 0) {
                    inicio = pos;
                }
                profundidade++;
            } else if (c === "}" && profundidade > 0) {
                profundidade--;

                if (profundidade === 0 && inicio !== -1) {
                    const bruto = texto.slice(inicio, pos + 1);
                    inicio = -1;

                    try {
                        aoObjeto(JSON.parse(bruto));
                    } catch (erro) {
                        console.error("JSON inválido na resposta:", bruto);
                    }
                }
            }
        }

        // nada em andamento: descarta o que já foi processado
        if (profundidade === 0) {
            texto = "";
            pos = 0;
        }
    };
}

// Devolve { quantidade, texto }: quantos objetos chegaram e o texto bruto (para depuração).
export async function lerObjetosJSON(response, aoReceber) {
    let quantidade = 0;
    let texto = "";

    const extrair = criarExtrator(function (objeto) {
        quantidade++;
        aoReceber(objeto);
    });

    if (response.body && response.body.getReader) {
        const leitor = response.body.getReader();
        const decodificador = new TextDecoder();

        while (true) {
            const { done, value } = await leitor.read();
            if (done) {
                break;
            }

            const pedaco = decodificador.decode(value, { stream: true });
            texto += pedaco;
            extrair(pedaco);
        }

        const resto = decodificador.decode();
        if (resto) {
            texto += resto;
            extrair(resto);
        }
    } else {
        texto = await response.text();
        extrair(texto);
    }

    return { quantidade: quantidade, texto: texto };
}