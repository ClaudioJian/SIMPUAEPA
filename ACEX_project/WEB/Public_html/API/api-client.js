/*
  +-------------------------------------------------------------------------------------------------+
  |Esse arquivo é para que seu pedido para backend pode ser válido, Você precisa incluir esse sempre|
  |                                                                                                 |
  |                                        Table of content                                         |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/


const csrf_header_name = 'X-CSRF-TOKEN';
const csrf_require_request = ['POST','DELETE','PATCH','PUT'];
const originalFetch = window.fetch;

interceptFetch();
document.addEventListener('DOMContentLoaded',()=>{
    document.addEventListener('submit',interceptFormRequest);
});


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
        const url = args[0] || window.location.href;
        const options = args[1] || {};
        const method = (options?.method || 'GET').toUpperCase();

        if(!csrf_require_request.includes(method)) return originalFetch(url,options);

        //always send to server, the cookie is send back only when don't have token in server or is expired
        if (!getCookieValue(csrf_header_name)) await renewCSRFToken();
        
        return await sendFetchRequest(url,options);
    }
}

async function interceptFormRequest(e) {
    const form = e.target;
    if(!form || form.tagName !=='FORM') return;

    const method = (form.method || 'POST').toUpperCase();
    if(!csrf_require_request.includes(method)) return;

    e.preventDefault();

    //hidden input insertion
    appendHiddenCSRFInput(form);

    if (!getCookieValue(csrf_header_name)) {
        //always send to server, the cookie is send back only when don't have token in server or is expired
        await renewCSRFToken();
        appendHiddenCSRFInput(form); 
    }

    console.log(form);
    HTMLFormElement.prototype.submit.call(form);
}

async function sendFetchRequest(url,options){
    let cookieVal = getCookieValue(csrf_header_name);
    const headers = new Headers(options.headers);
    options.headers = headers;
    headers.set(csrf_header_name,cookieVal);

    return originalFetch(url,options);
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

function appendHiddenCSRFInput(form){
    const cookieVal = getCookieValue(csrf_header_name);
    const input = form.querySelector(`input[name="${csrf_header_name}"]`);
    if(!input){
        const newInput = document.createElement('input');
        newInput.type = 'hidden';
        newInput.name = csrf_header_name;
        newInput.value = cookieVal;

        form.appendChild(newInput);
    }else input.value = cookieVal||'';
}

