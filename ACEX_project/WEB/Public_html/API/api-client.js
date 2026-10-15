/*
  +-------------------------------------------------------------------------------------------------+
  |Esse arquivo é para que seu pedido para backend pode ser válido, Você precisa incluir esse sempre|
  +-------------------------------------------------------------------------------------------------+
*/


const csrf_header_name = 'X-CSRF-TOKEN';
const nonce_sep = '#';
const csrf_require_request = ['POST','DELETE','PATCH','PUT'];
const originalFetch = window.fetch;


interceptFetch();
document.addEventListener('DOMContentLoaded',()=>{
    document.addEventListener('submit',interceptFormRequest);
});

/*    const replayBtn = document.getElementById("replay_attack");
    replayBtn.addEventListener('click',()=>{
        if(localStorage.getItem("replay_cResponse") === null) console.log("not stored yet");
        const cResponse = localStorage.getItem("replay_cResponse");
        const cgenTime = localStorage.getItem("replay_cgenTime");
        const snonce = localStorage.getItem("replay_snonce");
        const cnonce = localStorage.getItem("replay_cnonce");
        const url = localStorage.getItem("replay_url");
        const method = localStorage.getItem("replay_method");

        let headers = new Headers();
        const options = {method:method,body:"",headers:headers};

        headers.set('x-nonce-response',cResponse);
        headers.set('x-cgen-time',cgenTime);
        headers.set('x-snonce',snonce);
        headers.set('x-cnonce',cnonce);
        options.headers = headers;

        originalFetch(url,options);
    });
*/



function getCookieValue(cookieName){
    if(!document.cookie) return null;
    //document.cookie is some like cookie1=name; flag1=value2;cookie2=name2...
    const cookies = document.cookie.split("; ");

    for(let part of cookies){
        part = part.trim();
        const equalsIndex = part.indexOf("=");

        //not exist
        if (equalsIndex === -1) continue;

        const name = part.slice(0, equalsIndex).trim();
        const val = part.slice(equalsIndex + 1).trim();

        if(name!=cookieName) continue;
        return decodeURIComponent(val);
    }
    return null;
}

async function interceptFetch(){
    window.fetch = async(...args)=>{
        const input = args[0] || window.location.href;

        let options = {...(args[1] || {})};
        let method;
        let headers;
        let url;

        if(input instanceof Request){
            method = input.method;
            url = input.url;
            headers = new Headers(input.headers);
        }else {
            method = (options.method || "GET").toUpperCase();
            url = args[0] || window.location.href;
            headers = new Headers(options.headers);
        }      

        if(csrf_require_request.includes(method)) headers = await appendCSRFHeader(method,headers);
        const cgenTime = Date.now();
        headers.append('x-cgen-time',cgenTime);

        options.method = method;
        options.headers = headers;

        //preserve original
        const inputClone = input instanceof Request ? input.clone() : url;
        const retryInput = input instanceof Request ? input.clone() : url;

        const serverResponse = await originalFetch(inputClone,options);
        if(serverResponse.status !== 401) return serverResponse;

        return await HandleChallenge(retryInput,options,serverResponse);
    }
}

async function appendCSRFHeader(method,headers){
    if(csrf_require_request.includes(method)) {
        //always send to server, the cookie is send back only when don't have token in server or is expired
        if (!getCookieValue(csrf_header_name)) await renewCSRFToken();
        const cookieVal = getCookieValue(csrf_header_name);
        
        if(cookieVal) headers.set(csrf_header_name,cookieVal);
    }
    return headers;
}

async function interceptFormRequest(e) {
    const form = e.target;
    if(!form || form.tagName !=='FORM') return;

    e.preventDefault();
    e.stopPropagation();

    const url = form.action || window.location.href;
    const method = (form.method || 'POST').toUpperCase();
    const data = new FormData(form);
    let headers = new Headers();
    
    if(csrf_require_request.includes(method)) headers = await appendCSRFHeader(method,headers);
    
    const cgenTime = Date.now();
    headers.append('x-cgen-time',cgenTime);
    
    const options = {method:method,headers:headers};
    if(method!== 'HEAD' && method!== 'GET') options.body = data;

    let serverResponse = await originalFetch(url,options);

    if(serverResponse.status === 401) serverResponse = await HandleChallenge(url,options,serverResponse);


    const body = await serverResponse.clone().text();
    //window.location.href = serverResponse.redirected ? serverResponse.url : url;
}

async function HandleChallenge(url,options,serverResponse){
    if(!serverResponse.headers) return serverResponse;

    const snonce = serverResponse.headers.get('x-snonce');
    if(!snonce || snonce==='') return serverResponse;

    const inputClone = url instanceof Request ? url.clone():url;
    const updatedheaders = await SetNonceHeaders(inputClone,snonce,options.method,new Headers(options.headers));

    const optionsClone = {...options, headers:updatedheaders};


    return await originalFetch(inputClone,optionsClone);
}

async function SetNonceHeaders(url,snonce,method,headers){
    const path = new URL(url,window.location.origin).pathname;

    const rawCnonce = window.crypto.getRandomValues(new Uint8Array(32));
    const cnonce = Array.from(rawCnonce, byte => byte.toString(16).padStart(2, '0')).join('');
    const cgenTime = headers.get('x-cgen-time');

    const cResponse = await GenerateResponse(snonce,method,path,cgenTime,cnonce);

    headers.set('x-nonce-response',cResponse);
    headers.set('x-cgen-time',cgenTime);
    headers.set('x-snonce',snonce);
    headers.set('x-cnonce',cnonce);
    
    //store_replay(cResponse,cgenTime,snonce,cnonce,url,method);
    return headers;
}

async function GenerateResponse(snonce,method,path,cgen_time,cnonce){
    const A2 = await digestMessage(method.toUpperCase() + ":" + path);//H(method:url), now hexed hash string

    const msg = cnonce + nonce_sep + snonce + nonce_sep + cgen_time + nonce_sep + A2;
    const response = await digestMessage(msg);//now hexed hash string
    return response;
}
    

/**
 * from: https://developer.mozilla.org/en-US/docs/Web/API/SubtleCrypto/digest
 */
async function digestMessage(message) {
  const msgUint8 = new TextEncoder().encode(message); // encode as (utf-8) Uint8Array
  const hashBuffer = await window.crypto.subtle.digest("SHA-256", msgUint8); // hash the message
  if (Uint8Array.prototype.toHex) {
    // Use toHex if supported.
    return new Uint8Array(hashBuffer).toHex(); // Convert ArrayBuffer to hex string.
  }
  // If toHex() is not supported, fall back to an alternative implementation.
  const hashArray = Array.from(new Uint8Array(hashBuffer)); // convert buffer to byte array
  const hashHex = hashArray
    .map((b) => b.toString(16).padStart(2, "0"))
    .join(""); // convert bytes to hex string
  return hashHex;
}



async function renewCSRFToken(){
    let cookieVal = getCookieValue(csrf_header_name);
    if (cookieVal) return;

    await originalFetch('/SIMPUAEPA/csrf',{
        method:'POST',
        headers:{
            [csrf_header_name] : ""
        }
    });
}

/*
function store_replay(cResponse,cgenTime,snonce,cnonce,url,method){
    if(localStorage.getItem("replay_cResponse") === null) localStorage.setItem("replay_cResponse",cResponse);
    if(localStorage.getItem("replay_cgenTime") === null) localStorage.setItem("replay_cgenTime",cgenTime);
    if(localStorage.getItem("replay_snonce") === null) localStorage.setItem("replay_snonce",snonce);
    if(localStorage.getItem("replay_cnonce") === null) localStorage.setItem("replay_cnonce",cnonce);
    if(localStorage.getItem("replay_url") === null) localStorage.setItem("replay_url",url);
    if(localStorage.getItem("replay_method") === null) localStorage.setItem("replay_method",method);
}
*/
