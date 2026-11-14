
    import * as L from '../UI/leaflet/leaflet.js'; // importa/extende o arquivo do leaflet para usar no javascript.
    import { iniciarReclamacoes } from './reclamacoes.js';

    








    // função para fazer o botão aparecer o controle de icones.
    function show_it(element,item) {
        const testbtn = document.getElementById(element);
        const visualizacao = document.getElementById(item);

            if (!testbtn || !visualizacao || testbtn.dataset.eventoAdicionado) {
            return;
        }
        testbtn.dataset.eventoAdicionado = "true";


        testbtn.addEventListener('click', () => {
        const visivel = visualizacao.classList.toggle('visivel');
    
    });    
    };

    // função para fazer o botão aparecer o controle de icones.
    
    show_it("test","visualizacao");
    show_it("fechar","caixa_reclamacao")

    //console log para ver se os elementos estão sendo transmitidos(isso garante que o mapa esteja funcionando com as extensões)
    console.log(L);
const map = new L.Map('map', {
    center: [-23.6579, -46.52720],
        zoom: 16,
        maxZoom:19,
        minZoom:12,
        zoomControl: false
});
    var normal = new L.TileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { 
    maxZoom: 19, 
    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>' 
        //
}).addTo(map);
    const zoom = new L.ZoomControl({ position: "bottomright",
        zoom: 16,
        maxZoom:19,
        minZoom:12
    }).addTo(map);


    let limiteSantoAndre = null;
    let dadosSantoAndre = null;

    async function carregarLimite() {
    const resposta = await fetch("UI/dados/santo-andre.geojson");

    if (!resposta.ok) {
        throw new Error(
            `Erro ao carregar GeoJSON: ${resposta.status}`
        );
    }

    const dados = await resposta.json();

    dadosSantoAndre = dados;

    limiteSantoAndre = new L.GeoJSON(dados, {
        style: {
            color: "#166534",
            weight: 3,
            fill: false
        }
    });

    // Obtém os limites do município.
    const limites = limiteSantoAndre.getBounds();

    




























    // Cria um retângulo maior que o município para cobrir o exterior.
    const limitesExternos = limites.pad(20);

    const anelExterior = [
        [limitesExternos.getSouth(), limitesExternos.getWest()],
        [limitesExternos.getSouth(), limitesExternos.getEast()],
        [limitesExternos.getNorth(), limitesExternos.getEast()],
        [limitesExternos.getNorth(), limitesExternos.getWest()]
    ];

    // Reúne os anéis do GeoJSON, convertendo [longitude, latitude]
    // para [latitude, longitude], formato usado pelo Leaflet.
    const aneisMunicipio = [];

    for (const feature of dados.features) {
        const geometria = feature.geometry;
        if (!geometria) continue;

        let poligonos = [];

        if (geometria.type === "Polygon") {
            poligonos = [geometria.coordinates];
        } else if (geometria.type === "MultiPolygon") {
            poligonos = geometria.coordinates;
        }

        for (const poligono of poligonos) {
            for (const anel of poligono) {
                aneisMunicipio.push(
                    anel.map(([longitude, latitude]) => [
                        latitude,
                        longitude
                    ])
                );
            }
        }
    }

    // Pinta o exterior e deixa o interior do município transparente.
    const mascaraExterior = new L.Polygon(
        [anelExterior, ...aneisMunicipio],
        {
            stroke: false,
            fill: true,
            fillColor: "#111827",
            fillOpacity: 0.20,
            fillRule: "evenodd",
            interactive: false
        }
    );

    map.addLayer(mascaraExterior);

    // Desenha o contorno por cima da máscara.
    map.addLayer(limiteSantoAndre);
    limiteSantoAndre.bringToFront();

    // Mantém a restrição de navegação já existente.
    const areaPermitida = limites.pad(0.10);

    map.setMaxBounds(areaPermitida);
    map.options.maxBoundsViscosity = 1.0;

    // Enquadra Santo André no mapa.
    map.setView([-23.6579, -46.52720], 15);
}

    carregarLimite().catch(console.error);


    //icone default

    const icon_Height = 50;
    const icon_Width = 55;
    
    var defaultIcon = new L.Icon({
    iconUrl: 'images/icones/default.png', // Aqui é onde define a imagem do icone, necessário a pasta para organização
    //shadowUrl: 'leaf-shadow.png', // não vamos fazer com sombra, fica estranho e é desnecessário

    iconSize:     [icon_Width, icon_Height], // size of the icon
    shadowSize:   [50, 64], // size of the shadow
    iconAnchor:   [icon_Width/2, icon_Height], // point of the icon which will correspond to marker's location
    shadowAnchor: [4, 62],  // the same for the shadow
    popupAnchor:  [0, -47] // point from which the popup should open relative to the iconAnchor
});
    //entrada para fazer o botão clicavel
    const html = "<button id='reclamacao'>Quer fazer uma reclamação?</button>"
    
    let dentro = false;

    
    // Verifica se um ponto está dentro de um anel do GeoJSON.
    function pontoDentroDoAnel(ponto, anel) {
        const x = ponto.lng;
        const y = ponto.lat;
        let dentro = false;

        for (let i = 0, j = anel.length - 1; i < anel.length; j = i++) {
            const [xi, yi] = anel[i];
            const [xj, yj] = anel[j];

            const cruza =
                (yi > y) !== (yj > y) &&
                x < ((xj - xi) * (y - yi)) / (yj - yi) + xi;

            if (cruza) {
                dentro = !dentro;
            }
        }

        return dentro;
    }

    // Verifica o contorno externo e os eventuais buracos do polígono.
    function pontoDentroDoPoligono(ponto, coordenadas) {
        if (!pontoDentroDoAnel(ponto, coordenadas[0])) {
            return false;
        }

        for (let i = 1; i < coordenadas.length; i++) {
            if (pontoDentroDoAnel(ponto, coordenadas[i])) {
                return false;
            }
        }

        return true;
    }

    // Verifica todos os polígonos de Santo André.
    function pontoDentroDeSantoAndre(ponto, geojson) {
        for (const feature of geojson.features) {
            const geometria = feature.geometry;

            if (!geometria) continue;

            if (geometria.type === "Polygon") {
                if (pontoDentroDoPoligono(ponto, geometria.coordinates)) {
                    return true;
                }
            }

            if (geometria.type === "MultiPolygon") {
                for (const poligono of geometria.coordinates) {
                    if (pontoDentroDoPoligono(ponto, poligono)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    function restringirDragSantoAndre(marcador) {
    let ultimaPosicaoValida = marcador.getLatLng();

    marcador.on("drag", (evento) => {
        const marcadorAtual = evento.target;
        const novaPosicao = marcadorAtual.getLatLng();

        if (!pontoDentroDeSantoAndre(novaPosicao, dadosSantoAndre)) {
            marcadorAtual.setLatLng(ultimaPosicaoValida);
        } else {
            ultimaPosicaoValida = novaPosicao;
        }
    });
}

    // Marcadores criados pelo clique no mapa.
    let allMarker = [];

    function clickOnMapDefault(e) {
        // Espera o GeoJSON terminar de carregar.
        if (!dadosSantoAndre || !limiteSantoAndre) {
            return;
        }

        // Impede a criação de marcadores fora de Santo André.
        if (!pontoDentroDeSantoAndre(e.latlng, dadosSantoAndre)) {
            console.log("Clique fora da área permitida.");
            return;
        }

        // Remove o marcador anterior, se existir.
        allMarker.forEach(element => {
            map.removeLayer(element);
        });

        allMarker = [];

        // Cria o novo marcador na posição clicada.
        const defaultMarkerPopup = new L.Marker(e.latlng, {
    icon: defaultIcon,
    draggable: true,
    interactive: true
}).bindPopup(html);

restringirDragSantoAndre(defaultMarkerPopup);
// Guarda a última posição válida do marcador.
let ultimaPosicaoValida = e.latlng;

// Verifica a posição quando o usuário termina de arrastar.
defaultMarkerPopup.on("drag", (evento) => {
    const marcador = evento.target;
    const novaPosicao = marcador.getLatLng();

    if (!pontoDentroDeSantoAndre(novaPosicao, dadosSantoAndre)) {
        marcador.setLatLng(ultimaPosicaoValida);
    } else {
        ultimaPosicaoValida = novaPosicao;
    }
});

defaultMarkerPopup.addTo(map);
allMarker.push(defaultMarkerPopup);
    }

    // Ativa o evento de clique no mapa.
    map.on("click", clickOnMapDefault);

    
    // Configura o botão que aparece dentro do popup do marcador.
    map.on("popupopen", (e) => {
        const popupElement = e.popup.getElement();
        if (!popupElement) return;

        const botao = popupElement.querySelector("#reclamacao");
        if (!botao) return;

        botao.onclick = () => {
    const caixa = document.getElementById("caixa_reclamacao");

    if (caixa) {
        caixa.classList.remove("visivel");
    }
};
    });


    //Declaração de cada icone
    //Importante o IconAnchor estar errado não é natural, isso zoa com o sentido do ponto no mapa, somente para o protótipo
    var perigoIcon = new L.Icon({
        iconUrl: 'images/icones/perigo.png',
        iconSize:     [50, 50],
        iconAnchor:   [icon_Width / 2, icon_Height],
        popupAnchor:  [0, -47]
    }),  
    // também com outros icones
    outroIcon = new L.Icon({
        iconUrl: 'images/icones/outro.png',
        iconSize:     [50, 50],
        iconAnchor:   [icon_Width / 2, icon_Height],
        popupAnchor:  [0, -47]
    }),    
    // icone de esgoto
    esgotoIcon = new L.Icon({
        iconUrl: 'images/icones/esgoto.png',
        iconSize:     [50, 50],
        iconAnchor:   [icon_Width / 2, icon_Height],
        popupAnchor:  [0, -47]
    }),
    //  icone de poluição
    poluicaoIcon = new L.Icon({
        iconUrl: 'images/icones/poluicao.png',
        iconSize:     [50, 50],
        iconAnchor:   [icon_Width / 2, icon_Height],
        popupAnchor:  [0, -47]
    }),
    //  icone de poluição sonora
    sonoraIcon = new L.Icon({
        iconUrl: 'images/icones/sonora.png',
        iconSize:     [50, 50],
        iconAnchor:   [icon_Width / 2, icon_Height],
        popupAnchor:  [0, -47]
    }),
    //  icone da rua
    ruaIcon = new L.Icon({
        iconUrl: 'images/icones/rua.png',
        iconSize:     [50, 50],
        iconAnchor:   [icon_Width / 2, icon_Height],
        popupAnchor:  [0, -47]
    }),
    // icone de eletricidade
    eletricidadeIcon = new L.Icon({
        iconUrl: 'images/icones/eletricidade.png',
        iconSize:     [50, 50],
        iconAnchor:   [icon_Width / 2, icon_Height],
        popupAnchor:  [0, -47]
    });
    
//Separação dos icones
//TESTE de agrupamento
/*
//Separação do  icone de perigo
    const conjunto_perigos = new L.LayerGroup([
    new L.Marker([-23.660, -46.553], {icon: perigoIcon}).bindPopup("Eu sou uma marcação de perigo."),
    new L.Marker([-23.660, -46.555], {icon: perigoIcon}).bindPopup("Eu sou uma marcação de perigo."),
    new L.Marker([-23.660, -46.557], {icon: perigoIcon}).bindPopup("Eu sou uma marcação de perigo.")]);

//Separação do icone de outro

const conjunto_outros = new L.LayerGroup([
    new L.Marker([-23.661, -46.553], {icon: outroIcon}).bindPopup("Eu sou uma marcação de outro."),
    new L.Marker([-23.661, -46.555], {icon: outroIcon}).bindPopup("Eu sou uma marcação de outro."),
    new L.Marker([-23.661, -46.557], {icon: outroIcon}).bindPopup("Eu sou uma marcação de outro.")]); 

//Separação do icone de esgoto

const conjunto_esgotos = new L.LayerGroup([
    new L.Marker([-23.662, -46.553], {icon: esgotoIcon}).bindPopup("Eu sou uma marcação de esgoto."),
    new L.Marker([-23.662, -46.555], {icon: esgotoIcon}).bindPopup("Eu sou uma marcação de esgoto."),
    new L.Marker([-23.662, -46.557], {icon: esgotoIcon}).bindPopup("Eu sou uma marcação de esgoto.")]);

//Separação do icone de poluição

const conjunto_poluicoes = new L.LayerGroup([
    new L.Marker([-23.663, -46.553], {icon: poluicaoIcon}).bindPopup("Eu sou uma marcação de poluição."),
    new L.Marker([-23.663, -46.555], {icon: poluicaoIcon}).bindPopup("Eu sou uma marcação de poluição."),
    new L.Marker([-23.663, -46.557], {icon: poluicaoIcon}).bindPopup("Eu sou uma marcação de poluição.")]);

//Separação do icone de poluição sonora

const conjunto_sonoros = new L.LayerGroup([
    new L.Marker([-23.664, -46.553], {icon: sonoraIcon}).bindPopup("Eu sou uma marcação de poluição sonora."),
    new L.Marker([-23.664, -46.555], {icon: sonoraIcon}).bindPopup("Eu sou uma marcação de poluição sonora."),
    new L.Marker([-23.664, -46.557], {icon: sonoraIcon}).bindPopup("Eu sou uma marcação de poluição sonora.")]);

//Separação do icone de problema na rua

const conjunto_ruas = new L.LayerGroup([
    new L.Marker([-23.665, -46.553], {icon: ruaIcon}).bindPopup("Eu sou uma marcação de problemas na rua."),
    new L.Marker([-23.665, -46.555], {icon: ruaIcon}).bindPopup("Eu sou uma marcação de problemas na rua."),
    new L.Marker([-23.665, -46.557], {icon: ruaIcon}).bindPopup("Eu sou uma marcação de problemas na rua.")]);

//Separação do icone de eletricidade

const conjunto_eletricidades = new L.LayerGroup([
    new L.Marker([-23.666, -46.553], {icon: eletricidadeIcon}).bindPopup("Eu sou uma marcação de eletricidade."),
    new L.Marker([-23.666, -46.555], {icon: eletricidadeIcon}).bindPopup("Eu sou uma marcação de eletricidade."),
    new L.Marker([-23.666, -46.557], {icon: eletricidadeIcon}).bindPopup("Eu sou uma marcação de eletricidade.")]);

const conjunto_icones = new L.LayerGroup([
            
        conjunto_outros,
        conjunto_perigos, 
        conjunto_esgotos,
        conjunto_poluicoes,
        conjunto_sonoros,
        conjunto_ruas,
        conjunto_eletricidades
    ]);
*/

//outro parte de exemplos dos icones, porém esse é exemplos similares a realidade com os icones e reclamações.

// Conjuntos de reclamações de demonstração
let conjunto_perigos = new L.LayerGroup();
let conjunto_outros = new L.LayerGroup();
let conjunto_esgotos = new L.LayerGroup();
let conjunto_poluicoes = new L.LayerGroup();
let conjunto_sonoros = new L.LayerGroup();
let conjunto_ruas = new L.LayerGroup();
let conjunto_eletricidades = new L.LayerGroup();

// Agrupamento geral de todos os conjuntos
const conjunto_icones = new L.LayerGroup([
    conjunto_outros,
    conjunto_perigos,
    conjunto_esgotos,
    conjunto_poluicoes,
    conjunto_sonoros,
    conjunto_ruas,
    conjunto_eletricidades
]);

// Gera uma localização aleatória dentro de Santo André
function gerarLocalizacaoAleatoria() {
    const limites = limiteSantoAndre.getBounds();

    let latitude;
    let longitude;
    let tentativas = 0;

    do {
        latitude =
            limites.getSouth() +
            Math.random() * (limites.getNorth() - limites.getSouth());

        longitude =
            limites.getWest() +
            Math.random() * (limites.getEast() - limites.getWest());

        tentativas++;

        if (tentativas > 10000) {
            throw new Error("Não foi possível encontrar um ponto dentro de Santo André.");
        }

    } while (
        !pontoDentroDeSantoAndre(
            { lat: latitude, lng: longitude },
            dadosSantoAndre
        )
    );

    return {
        latitude: latitude,
        longitude: longitude
    };
}

// Cria uma reclamação de demonstração
function criarReclamacaoExemplo(dados, icone, conjunto) {

    const localizacao = gerarLocalizacaoAleatoria();

    const reclamacao = {
        tipo: dados.tipo,
        titulo: dados.titulo,
        descricao: dados.descricao,
        referencia: dados.referencia,

        localizacao: {
            latitude: localizacao.latitude,
            longitude: localizacao.longitude
        }
    };

    const marcador = new L.Marker(
        [
            reclamacao.localizacao.latitude,
            reclamacao.localizacao.longitude
        ],
        {
            icon: icone,
            draggable: false,
            interactive: true
        }
    );

    marcador.bindPopup(criarPopupReclamacao(reclamacao));

    conjunto.addLayer(marcador);

    return marcador;
}

function criarConjuntosExemplo() {

    // PERIGO
    const exemplosPerigos = [
        {
            tipo: "perigo",
            titulo: "Poste com risco de queda",
            descricao: "Poste apresenta inclinação acentuada e aparenta risco de queda.",
            referencia: "Próximo a uma área residencial"
        },
        {
            tipo: "perigo",
            titulo: "Calçada com estrutura danificada",
            descricao: "Trecho da calçada apresenta rachaduras e partes soltas.",
            referencia: "Via pública"
        },
        {
            tipo: "perigo",
            titulo: "Bueiro sem proteção",
            descricao: "Bueiro encontra-se sem tampa, oferecendo risco aos pedestres.",
            referencia: "Próximo a um cruzamento"
        }
    ];

    // OUTROS
    const exemplosOutros = [
        {
            tipo: "outro",
            titulo: "Lixeira pública danificada",
            descricao: "Lixeira apresenta danos e não está sendo utilizada corretamente.",
            referencia: "Área pública"
        },
        {
            tipo: "outro",
            titulo: "Placa de sinalização caída",
            descricao: "Placa de sinalização encontra-se caída e precisa de manutenção.",
            referencia: "Próximo à via"
        },
        {
            tipo: "outro",
            titulo: "Banco público quebrado",
            descricao: "Banco de praça apresenta danos em sua estrutura.",
            referencia: "Área de convivência"
        }
    ];

    // ESGOTO
    const exemplosEsgotos = [
        {
            tipo: "esgoto",
            titulo: "Vazamento de esgoto",
            descricao: "Há vazamento de esgoto na via pública.",
            referencia: "Próximo a residências"
        },
        {
            tipo: "esgoto",
            titulo: "Bueiro com retorno de esgoto",
            descricao: "O bueiro apresenta retorno de água com aspecto de esgoto.",
            referencia: "Rua residencial"
        },
        {
            tipo: "esgoto",
            titulo: "Mau cheiro próximo à rede",
            descricao: "Foi identificado um forte odor próximo à rede de esgoto.",
            referencia: "Via pública"
        }
    ];

    // POLUIÇÃO
    const exemplosPoluicoes = [
        {
            tipo: "poluicao",
            titulo: "Descarte irregular de resíduos",
            descricao: "Acúmulo de resíduos descartados irregularmente em área pública.",
            referencia: "Terreno aberto"
        },
        {
            tipo: "poluicao",
            titulo: "Entulho em calçada",
            descricao: "Materiais de construção estão ocupando parte da calçada.",
            referencia: "Próximo a residências"
        },
        {
            tipo: "poluicao",
            titulo: "Acúmulo de lixo",
            descricao: "Há grande quantidade de lixo acumulado em local inadequado.",
            referencia: "Área pública"
        }
    ];

    // POLUIÇÃO SONORA
    const exemplosSonoros = [
        {
            tipo: "sonora",
            titulo: "Ruído excessivo",
            descricao: "Relato de ruídos frequentes acima do esperado na região.",
            referencia: "Área residencial"
        },
        {
            tipo: "sonora",
            titulo: "Obra com ruído intenso",
            descricao: "Equipamentos de construção estão gerando ruído excessivo.",
            referencia: "Próximo a residências"
        },
        {
            tipo: "sonora",
            titulo: "Som alto em estabelecimento",
            descricao: "Relato de som alto recorrente durante o período noturno.",
            referencia: "Região comercial"
        }
    ];

    // PROBLEMAS NA RUA
    const exemplosRuas = [
        {
            tipo: "rua",
            titulo: "Buraco na via",
            descricao: "Buraco de grandes dimensões prejudica a circulação de veículos.",
            referencia: "Próximo a um cruzamento"
        },
        {
            tipo: "rua",
            titulo: "Asfalto danificado",
            descricao: "O asfalto apresenta rachaduras e irregularidades.",
            referencia: "Via pública"
        },
        {
            tipo: "rua",
            titulo: "Calçada irregular",
            descricao: "Trecho da calçada apresenta desníveis que dificultam a passagem.",
            referencia: "Área de pedestres"
        }
    ];

    // ELETRICIDADE
    const exemplosEletricidades = [
        {
            tipo: "eletricidade",
            titulo: "Lâmpada de iluminação pública apagada",
            descricao: "Ponto de iluminação pública não está funcionando.",
            referencia: "Via residencial"
        },
        {
            tipo: "eletricidade",
            titulo: "Fiação exposta",
            descricao: "Há fiação exposta em estrutura da rede elétrica.",
            referencia: "Via pública"
        },
        {
            tipo: "eletricidade",
            titulo: "Semáforo apagado",
            descricao: "Semáforo encontra-se sem funcionamento.",
            referencia: "Cruzamento"
        }
    ];

    // Adiciona os exemplos aos respectivos conjuntos
    exemplosPerigos.forEach(dado =>
        criarReclamacaoExemplo(dado, perigoIcon, conjunto_perigos)
    );

    exemplosOutros.forEach(dado =>
        criarReclamacaoExemplo(dado, outroIcon, conjunto_outros)
    );

    exemplosEsgotos.forEach(dado =>
        criarReclamacaoExemplo(dado, esgotoIcon, conjunto_esgotos)
    );

    exemplosPoluicoes.forEach(dado =>
        criarReclamacaoExemplo(dado, poluicaoIcon, conjunto_poluicoes)
    );

    exemplosSonoros.forEach(dado =>
        criarReclamacaoExemplo(dado, sonoraIcon, conjunto_sonoros)
    );

    exemplosRuas.forEach(dado =>
        criarReclamacaoExemplo(dado, ruaIcon, conjunto_ruas)
    );

    exemplosEletricidades.forEach(dado =>
        criarReclamacaoExemplo(dado, eletricidadeIcon, conjunto_eletricidades)
    );

    console.log("Reclamações de demonstração criadas!");
}

//aqui acaba os exemplos

    //Função que seleciona o botão para aparecer ou tirar os icones do tipo do elemento.


    function pick_point (elemento,conjunto) {
        console.log(document.getElementById(elemento))
      const seletor = document.getElementById(elemento);

        if(!seletor) {
            console.error("Botão não encontrado!");
            
            return;
        }


        seletor.addEventListener('click', () => {
            if(map.hasLayer(conjunto)) {
                map.removeLayer(conjunto);
               
            } else {
                map.addLayer(conjunto);
                
            }
        });
    };
    
    //agrupamento da função que permite a visualização dos icones de cada tipo.

    pick_point("botao-perigo", conjunto_perigos);
    pick_point("botao-rua",conjunto_ruas)
    pick_point("botao-esgoto",conjunto_esgotos);
    pick_point("botao-sonora",conjunto_sonoros);   
    pick_point("botao-poluicao",conjunto_poluicoes);
    pick_point("botao-eletricidade",conjunto_eletricidades);
    pick_point("botao-outro",conjunto_outros);
    pick_point("botao-todos", conjunto_icones);   
        

        


    
const formularioReclamacao =
    document.getElementById("form_reclamacao");

const avisoPopup = document.getElementById("aviso_popup");
const fecharAviso = document.getElementById("fechar_aviso");

function abrirAviso(titulo, mensagem) {
    document.getElementById("aviso_titulo").textContent = titulo;
    document.getElementById("aviso_mensagem").textContent = mensagem;

    avisoPopup.classList.add("aberto");
    fecharAviso.focus();
}

function fecharPopupAviso() {
    avisoPopup.classList.remove("aberto");
}

fecharAviso.addEventListener("click", fecharPopupAviso);

// Também permite fechar clicando no fundo escuro.
avisoPopup.addEventListener("click", (evento) => {
    if (evento.target === avisoPopup) {
        fecharPopupAviso();
    }
});

// Fecha com a tecla Escape.
document.addEventListener("keydown", (evento) => {
    if (
        evento.key === "Escape" &&
        avisoPopup.classList.contains("aberto")
    ) {
        fecharPopupAviso();
    }
});


//criação da reclamação com o formulario







































//separa os icones para a criação do formulario

let reclamacoesMarcadores = [];

function obterIconeReclamacao(tipo) {
    const icones = {
        rua: ruaIcon,
        perigo: perigoIcon,
        eletricidade: eletricidadeIcon,
        esgoto: esgotoIcon,
        poluicao: poluicaoIcon,
        sonora: sonoraIcon, 
        outro:outroIcon
    };

    return icones[tipo] || defaultIcon;
}


//ATENÇÃO ISSO É SOMENTE PARA TESTE E PROTOTIPAGEM PARA APRESENTAÇÃO
//depois tirar para funcionar corretamente com backend

//verifica o texto dos elementos

function escaparHTML(texto) {
    return String(texto ?? "").replace(/[&<>"']/g, caractere => {
        const caracteres = {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        };

        return caracteres[caractere];
    });
}

//cria o elemento do formulario, além de utilizar a função anterior

function criarPopupReclamacao(reclamacao) {
    return `
        <div class="popup-reclamacao">

            <h3>${escaparHTML(reclamacao.titulo)}</h3>


            <p>
                ${escaparHTML(reclamacao.descricao)}
            </p>

            ${reclamacao.referencia ? `
                <p>
                    <strong>Referência:</strong>
                    ${escaparHTML(reclamacao.referencia)}
                </p>
            ` : ""}

        </div>
    `;
}


    





















// ================= RECLAMAÇÕES (back-end) =================

iniciarReclamacoes({
    L: L,
    map: map,

    // grupos de cada tipo (os botões do painel de ícones ligam e desligam cada um)
    grupos: {
        perigo: conjunto_perigos,
        outro: conjunto_outros,
        esgoto: conjunto_esgotos,
        poluicao: conjunto_poluicoes,
        sonora: conjunto_sonoros,
        rua: conjunto_ruas,
        eletricidade: conjunto_eletricidades
    },

    icones: {
        perigo: perigoIcon,
        outro: outroIcon,
        esgoto: esgotoIcon,
        poluicao: poluicaoIcon,
        sonora: sonoraIcon,
        rua: ruaIcon,
        eletricidade: eletricidadeIcon
    },

    // marcador provisório (o clique no mapa) e a limpeza dele depois de publicar
    obterMarcadorProvisorio: function () {
        return allMarker[0];
    },

    limparMarcadorProvisorio: function () {
        allMarker.forEach(function (marcador) {
            map.removeLayer(marcador);
        });
        allMarker = [];
    },

    abrirAviso: abrirAviso
});

// Para as reclamações já aparecerem ao abrir o mapa, sem precisar ligar cada tipo
// no painel de ícones, tire as barras da linha abaixo:
// conjunto_icones.addTo(map);
    
