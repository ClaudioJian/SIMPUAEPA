
    import * as L from './leaflet/leaflet.js'; // importa/extende o arquivo do leaflet para usar no javascript.


    


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
    const resposta = await fetch("javascript/dados/santo-andre.geojson");

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
    
    var defaultIcon = new L.Icon({
    iconUrl: 'images/icones/default.png', // Aqui é onde define a imagem do icone, necessário a pasta para organização
    //shadowUrl: 'leaf-shadow.png', // não vamos fazer com sombra, fica estranho e é desnecessário

    iconSize:     [55, 50], // size of the icon
    shadowSize:   [50, 64], // size of the shadow
    iconAnchor:   [0, 0], // point of the icon which will correspond to marker's location
    shadowAnchor: [4, 62],  // the same for the shadow
    popupAnchor:  [25, -3] // point from which the popup should open relative to the iconAnchor
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
        if (!botao || botao.dataset.eventoAdicionado) return;

        botao.dataset.eventoAdicionado = "true";

        botao.addEventListener("click", () => {
            const caixa = document.getElementById("caixa_reclamacao");

            if (caixa) {
                caixa.classList.toggle("visivel");
            }
        });
    });


    //Declaração de cada icone

    var perigoIcon = new L.Icon({
        iconUrl: 'images/icones/perigo.png',
        iconSize:     [50, 50],
        iconAnchor:   [0, 0],
        popupAnchor:  [25, -3]
    }),  
    // também com outros icones
    outroIcon = new L.Icon({
        iconUrl: 'images/icones/outro.png',
        iconSize:     [50, 50],
        iconAnchor:   [0, 0],
        popupAnchor:  [25, -3]
    }),    
    // icone de esgoto
    esgotoIcon = new L.Icon({
        iconUrl: 'images/icones/esgoto.png',
        iconSize:     [50, 50],
        iconAnchor:   [0, 0],
        popupAnchor:  [25, -3]
    }),
    //  icone de poluição
    poluicaoIcon = new L.Icon({
        iconUrl: 'images/icones/poluicao.png',
        iconSize:     [50, 50],
        iconAnchor:   [0, 0],
        popupAnchor:  [25, -3]
    }),
    //  icone de poluição sonora
    sonoraIcon = new L.Icon({
        iconUrl: 'images/icones/sonora.png',
        iconSize:     [50, 50],
        iconAnchor:   [0, 0],
        popupAnchor:  [25, -3]
    }),
    //  icone da rua
    ruaIcon = new L.Icon({
        iconUrl: 'images/icones/rua.png',
        iconSize:     [50, 50],
        iconAnchor:   [0, 0],
        popupAnchor:  [25, -3]
    }),
    // icone de eletricidade
    eletricidadeIcon = new L.Icon({
        iconUrl: 'images/icones/eletricidade.png',
        iconSize:     [50, 50],
        iconAnchor:   [0, 0],
        popupAnchor:  [25, -3]
    });
    
//Separação dos icones


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

formularioReclamacao.addEventListener("submit", (evento) => {
    evento.preventDefault();

    const dadosFormulario = new FormData(formularioReclamacao);

    const reclamacao = {
        tipo: dadosFormulario.get("tipo_reclamacao"),
        titulo: dadosFormulario.get("titulo_reclamacao"),
        descricao: dadosFormulario.get("descricao_reclamacao"),
        referencia: dadosFormulario.get("referencia_local")
    };

    console.log("Reclamação preenchida:", reclamacao);

    abrirAviso(
        "Formulário validado!",
        "Os dados foram preenchidos corretamente, mas ainda não foram salvos."
    );
});




    

    
