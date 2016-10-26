/*
 * Ext JS Library 1.1
 * Copyright(c) 2006-2007, Ext JS, LLC.
 * licensing@extjs.com
 * 
 * http://www.extjs.com/license
 */


Ext.DomHelper=function(){var _1=null;var _2=/^(?:br|frame|hr|img|input|link|meta|range|spacer|wbr|area|param|col)$/i;var _3=/^table|tbody|tr|td$/i;var _4=function(o){if(typeof o=="string"){return o;}var b="";if(!o.tag){o.tag="div";}b+="<"+o.tag;for(var _7 in o){if(_7=="tag"||_7=="children"||_7=="cn"||_7=="html"||typeof o[_7]=="function"){continue;}if(_7=="style"){var s=o["style"];if(typeof s=="function"){s=s.call();}if(typeof s=="string"){b+=" style=\""+s+"\"";}else{if(typeof s=="object"){b+=" style=\"";for(var _9 in s){if(typeof s[_9]!="function"){b+=_9+":"+s[_9]+";";}}b+="\"";}}}else{if(_7=="cls"){b+=" class=\""+o["cls"]+"\"";}else{if(_7=="htmlFor"){b+=" for=\""+o["htmlFor"]+"\"";}else{b+=" "+_7+"=\""+o[_7]+"\"";}}}}if(_2.test(o.tag)){b+="/>";}else{b+=">";var cn=o.children||o.cn;if(cn){if(cn instanceof Array){for(var i=0,_c=cn.length;i<_c;i++){b+=_4(cn[i],b);}}else{b+=_4(cn,b);}}if(o.html){b+=o.html;}b+="</"+o.tag+">";}return b;};var _d=function(o,_f){var el=document.createElement(o.tag||"div");var _11=el.setAttribute?true:false;for(var _12 in o){if(_12=="tag"||_12=="children"||_12=="cn"||_12=="html"||_12=="style"||typeof o[_12]=="function"){continue;}if(_12=="cls"){el.className=o["cls"];}else{if(_11){el.setAttribute(_12,o[_12]);}else{el[_12]=o[_12];}}}Ext.DomHelper.applyStyles(el,o.style);var cn=o.children||o.cn;if(cn){if(cn instanceof Array){for(var i=0,len=cn.length;i<len;i++){_d(cn[i],el);}}else{_d(cn,el);}}if(o.html){el.innerHTML=o.html;}if(_f){_f.appendChild(el);}return el;};var _16=function(_17,s,h,e){_1.innerHTML=[s,h,e].join("");var i=-1,el=_1;while(++i<_17){el=el.firstChild;}return el;};var ts="<table>",te="</table>",tbs=ts+"<tbody>",tbe="</tbody>"+te,trs=tbs+"<tr>",tre="</tr>"+tbe;var _23=function(tag,_25,el,_27){if(!_1){_1=document.createElement("div");}var _28;var _29=null;if(tag=="td"){if(_25=="afterbegin"||_25=="beforeend"){return;}if(_25=="beforebegin"){_29=el;el=el.parentNode;}else{_29=el.nextSibling;el=el.parentNode;}_28=_16(4,trs,_27,tre);}else{if(tag=="tr"){if(_25=="beforebegin"){_29=el;el=el.parentNode;_28=_16(3,tbs,_27,tbe);}else{if(_25=="afterend"){_29=el.nextSibling;el=el.parentNode;_28=_16(3,tbs,_27,tbe);}else{if(_25=="afterbegin"){_29=el.firstChild;}_28=_16(4,trs,_27,tre);}}}else{if(tag=="tbody"){if(_25=="beforebegin"){_29=el;el=el.parentNode;_28=_16(2,ts,_27,te);}else{if(_25=="afterend"){_29=el.nextSibling;el=el.parentNode;_28=_16(2,ts,_27,te);}else{if(_25=="afterbegin"){_29=el.firstChild;}_28=_16(3,tbs,_27,tbe);}}}else{if(_25=="beforebegin"||_25=="afterend"){return;}if(_25=="afterbegin"){_29=el.firstChild;}_28=_16(2,ts,_27,te);}}}el.insertBefore(_28,_29);return _28;};return{useDom:false,markup:function(o){return _4(o);},applyStyles:function(el,_2c){if(_2c){el=Ext.fly(el);if(typeof _2c=="string"){var re=/\s?([a-z\-]*)\:\s?([^;]*);?/gi;var _2e;while((_2e=re.exec(_2c))!=null){el.setStyle(_2e[1],_2e[2]);}}else{if(typeof _2c=="object"){for(var _2f in _2c){el.setStyle(_2f,_2c[_2f]);}}else{if(typeof _2c=="function"){Ext.DomHelper.applyStyles(el,_2c.call());}}}}},insertHtml:function(_30,el,_32){_30=_30.toLowerCase();if(el.insertAdjacentHTML){if(_3.test(el.tagName)){var rs;if(rs=_23(el.tagName.toLowerCase(),_30,el,_32)){return rs;}}switch(_30){case"beforebegin":el.insertAdjacentHTML("BeforeBegin",_32);return el.previousSibling;case"afterbegin":el.insertAdjacentHTML("AfterBegin",_32);return el.firstChild;case"beforeend":el.insertAdjacentHTML("BeforeEnd",_32);return el.lastChild;case"afterend":el.insertAdjacentHTML("AfterEnd",_32);return el.nextSibling;}throw"Illegal insertion point -> \""+_30+"\"";}var _34=el.ownerDocument.createRange();var _35;switch(_30){case"beforebegin":_34.setStartBefore(el);_35=_34.createContextualFragment(_32);el.parentNode.insertBefore(_35,el);return el.previousSibling;case"afterbegin":if(el.firstChild){_34.setStartBefore(el.firstChild);_35=_34.createContextualFragment(_32);el.insertBefore(_35,el.firstChild);return el.firstChild;}else{el.innerHTML=_32;return el.firstChild;}case"beforeend":if(el.lastChild){_34.setStartAfter(el.lastChild);_35=_34.createContextualFragment(_32);el.appendChild(_35);return el.lastChild;}else{el.innerHTML=_32;return el.lastChild;}case"afterend":_34.setStartAfter(el);_35=_34.createContextualFragment(_32);el.parentNode.insertBefore(_35,el.nextSibling);return el.nextSibling;}throw"Illegal insertion point -> \""+_30+"\"";},insertBefore:function(el,o,_38){return this.doInsert(el,o,_38,"beforeBegin");},insertAfter:function(el,o,_3b){return this.doInsert(el,o,_3b,"afterEnd","nextSibling");},insertFirst:function(el,o,_3e){return this.doInsert(el,o,_3e,"afterBegin");},doInsert:function(el,o,_41,pos,_43){el=Ext.getDom(el);var _44;if(this.useDom){_44=_d(o,null);el.parentNode.insertBefore(_44,_43?el[_43]:el);}else{var _45=_4(o);_44=this.insertHtml(pos,el,_45);}return _41?Ext.get(_44,true):_44;},append:function(el,o,_48){el=Ext.getDom(el);var _49;if(this.useDom){_49=_d(o,null);el.appendChild(_49);}else{var _4a=_4(o);_49=this.insertHtml("beforeEnd",el,_4a);}return _48?Ext.get(_49,true):_49;},overwrite:function(el,o,_4d){el=Ext.getDom(el);el.innerHTML=_4(o);return _4d?Ext.get(el.firstChild,true):el.firstChild;},createTemplate:function(o){var _4f=_4(o);return new Ext.Template(_4f);}};}();