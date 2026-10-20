
    import * as L from '/leaflet/leaflet.js'; // importa/extende o arquivo do leaflet para usar no javascript.

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
    zoom: 15,
    maxZoom:19,
    minZoom:13
    
});
    var normal = new L.TileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { 
    maxZoom: 19, 
    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>' 
        //
}).addTo(map);

    //icone default
    
    var defaultIcon = new L.Icon({
    iconUrl: 'icones/default.png', // Aqui é onde define a imagem do icone, necessário a pasta para organização
    //shadowUrl: 'leaf-shadow.png', // não vamos fazer com sombra, fica estranho e é desnecessário

    iconSize:     [55, 50], // size of the icon
    shadowSize:   [50, 64], // size of the shadow
    iconAnchor:   [0, 0], // point of the icon which will correspond to marker's location
    shadowAnchor: [4, 62],  // the same for the shadow
    popupAnchor:  [25, -3] // point from which the popup should open relative to the iconAnchor
});
    //entrada para fazer o botão clicavel
    const html = "<button id='reclamacao'>Quer fazer uma reclamação?</button>"
    
    
    ///comando para pegar o ponto no icone default
    var allMarker = Array(); 
    function clickOnMapDefault(e){

        if(allMarker.length === 0){

            var defaultMarkerPopup = new L.Marker([51.51, -0.09], {
            icon:defaultIcon,
            draggable: true,
            interactive:true
        }).bindPopup(html);
        
        defaultMarkerPopup

            .setLatLng(e.latlng)
            .addTo(map);
                    
        allMarker.push(defaultMarkerPopup);

        

    } else {
        allMarker.forEach(element => {
            map.removeLayer(element);
        });

        allMarker = Array();
        clickOnMapDefault(e);
        }    
    };

    

    map.on('click', clickOnMapDefault);

    map.on("popupopen", () => {
    show_it("reclamacao", "caixa_reclamacao");
    });
    
    //Declaração de cada icone

    var perigoIcon = new L.Icon({
        iconUrl: 'icones/perigo.png',
        iconSize:     [50, 50],
        iconAnchor:   [0, 0],
        popupAnchor:  [25, -3]
    }),  
    // também com outros icones
    outroIcon = new L.Icon({
        iconUrl: 'icones/outro.png',
        iconSize:     [50, 50],
        iconAnchor:   [0, 0],
        popupAnchor:  [25, -3]
    }),    
    // icone de esgoto
    esgotoIcon = new L.Icon({
        iconUrl: 'icones/esgoto.png',
        iconSize:     [50, 50],
        iconAnchor:   [0, 0],
        popupAnchor:  [25, -3]
    }),
    //  icone de poluição
    poluicaoIcon = new L.Icon({
        iconUrl: 'icones/poluicao.png',
        iconSize:     [50, 50],
        iconAnchor:   [0, 0],
        popupAnchor:  [25, -3]
    }),
    //  icone de poluição sonora
    sonoraIcon = new L.Icon({
        iconUrl: 'icones/sonora.png',
        iconSize:     [50, 50],
        iconAnchor:   [0, 0],
        popupAnchor:  [25, -3]
    }),
    //  icone da rua
    ruaIcon = new L.Icon({
        iconUrl: 'icones/rua.png',
        iconSize:     [50, 50],
        iconAnchor:   [0, 0],
        popupAnchor:  [25, -3]
    }),
    // icone de eletricidade
    eletricidadeIcon = new L.Icon({
        iconUrl: 'icones/eletricidade.png',
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
        

        
    

    
