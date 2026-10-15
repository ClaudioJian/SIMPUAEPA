//import { LeafletMap, TileLayer } from '/leaflet/leaflet.js';   
    import * as L from '/leaflet/leaflet.js'; // mexer enderaçamento com a pasta funcionando doido
    
    const testbtn = document.getElementById('test');
    const visualizacao = document.getElementById('visualizacao');

    testbtn.addEventListener('click', () => {
    const visivel = visualizacao.classList.toggle('visivel');
    console.log(visivel);
    });    

    console.log(L);
const map = new L.Map('map', {
    center: [-23.6579, -46.52720],
    zoom: 15,
    maxZoom:19,
    minZoom:13
    //layers: [normal, conjunto_icones]
});
    var normal = new L.TileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { 
    maxZoom: 19, 
    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>' 
        //
}).addTo(map);
//const layerControl = new LayersControl(baseMaps, overlayMaps).addTo(map);
        //layerControl.addBaseLayer(normal, "map");
        

        //layerControl.addOverlay(conjunto_icones, "icones");


    
    var defaultIcon = new L.Icon({
    iconUrl: 'icones/default.png', // Aqui é onde define a imagem do icone, necessário a pasta para organização
    //shadowUrl: 'leaf-shadow.png', // não vamos fazer com sombra, fica estranho e é desnecessário

    iconSize:     [55, 50], // size of the icon
    shadowSize:   [50, 64], // size of the shadow
    iconAnchor:   [0, 0], // point of the icon which will correspond to marker's location
    shadowAnchor: [4, 62],  // the same for the shadow
    popupAnchor:  [25, -3] // point from which the popup should open relative to the iconAnchor
});

    var allMarker = Array(); 
    function clickOnMapDefault(e){
        if(allMarker.length === 0){
            var defaultMarkerPopup = new L.Marker([51.51, -0.09], {
            icon:defaultIcon,
            draggable: true,
            interactive:true
        }).bindPopup("Quer fazer uma reclamação?");
        defaultMarkerPopup
        .setLatLng(e.latlng)
        .addTo(map);
                    
        allMarker.push(defaultMarkerPopup);
    }
    else{
        allMarker.forEach(element => {
            map.removeLayer(element);
        });

        allMarker = Array();
        clickOnMapDefault(e);
    }
       
        


        
    };

    map.on('click', clickOnMapDefault);


    //var LeafIcon = new L.Icon({
    //options: {
        //shadowUrl: 'leaf-shadow.png', // não vamos fazer com sombra, fica estranho e é desnecessário
        //iconSize:     [50, 50],     //o tamanho do icone, idealmente 40-50 de tamanho
        //shadowSize:   [50, 64], // não vamos fazer com sombra, fica estranho e é desnecessário
        //iconAnchor:   [0, 0],     //o ponto de onde o ponto aparece em relação a marcação, melhor deixar zerado
        //shadowAnchor: [4, 62], // não vamos fazer com sombra, fica estranho e é desnecessário
        //popupAnchor:  [25, -3] // onde que aparece o texto, do que testado, essa séria a melhor configuração
        
    //}
//});
    //como declarar um ponto novo, dessa vez com outro icone
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
    //função anterior aos icones(retirar depois se continuar funcionando)
    //L.icon = function (options) {
    //return new L.Icon(options);
    //};
//Separação dos icones

//Separação do icone default
//const defaultponto = new L.Marker([-23.659, -46.555], {icon: defaultIcon}).addTo(map).bindPopup("Eu sou uma marcação default.")
//const conjunto_defaults =  new L.LayerGroup([defaultponto]);
//ao separar os elementos, podemos usar o método layerGroup da biblioteca leaflet para juntá-los.

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


    
    
    
    //const botao_controle_perigo = document.getElementById('botao-perigo'); //pega o botão
    //botao_controle_perigo.addEventListener("click",()=>{ //insere o valor no botão
   //     console.log(conjunto_perigos.document.getElementsByClassName("visivel").classList); //faz a leitura do comando a baixo
   //     botao_controle_perigo.classList.toggle('visivel');//pega o array conjunto_perigos e  coloca a classe visivel
   // });

   // const botao_controle_rua = pick_point(conjunto_ruas,'botao-rua','visivel');

    // //it_on = true;
    // !!Funciona o código para alterar os botões de ligar e desligar visu        
    // //const seletor = document.getElementById("botao-rua");

        //seletor.addEventListener('click', () => {
            //if(map.hasLayer(conjunto_ruas)) {
               // map.removeLayer(conjunto_ruas);
               //// it_on = false;
                //console.log("Marcadores de rua removidos");
            //} else {
               // map.addLayer(conjunto_ruas);
                ////it_on = true;
               // console.log("Marcadores de rua adicionados");
            //}
       // });


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
               // it_on = false;
                //console.log("Marcadores de rua removidos");
            } else {
                map.addLayer(conjunto);
                //it_on = true;
                //console.log("Marcadores de rua adicionados");
            }
        });
    };
    
    //agrupamento da função que permite a visualização dos icones de cada tipo

    pick_point("botao-perigo", conjunto_perigos);
    pick_point("botao-rua",conjunto_ruas)
    pick_point("botao-esgoto",conjunto_esgotos);
    pick_point("botao-sonora",conjunto_sonoros);   
    pick_point("botao-poluicao",conjunto_poluicoes);
    pick_point("botao-eletricidade",conjunto_eletricidades);
    pick_point("botao-outro",conjunto_outros);
    pick_point("botao-todos", conjunto_icones);   //fazer depois 
        

        
    

    
