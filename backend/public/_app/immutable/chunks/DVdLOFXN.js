import{c,p as e}from"./Dk7719-K.js";async function l(s,n={}){const{baseUrl:o,...r}=n,a=await c(s,{...r,baseUrl:o});let t=null;try{t=await a.json()}catch{t=null}return e(t,a.status)}export{l as r};
