/*
 * Ext JS Library 1.1
 * Copyright(c) 2006-2007, Ext JS, LLC.
 * licensing@extjs.com
 * 
 * http://www.extjs.com/license
 */


Ext.EventManager=function(){var _1,_2,_3=false;var _4,_5,_6,_7;var E=Ext.lib.Event;var D=Ext.lib.Dom;var _a=function(){if(!_3){_3=true;Ext.isReady=true;if(_2){clearInterval(_2);}if(Ext.isGecko||Ext.isOpera){document.removeEventListener("DOMContentLoaded",_a,false);}if(_1){_1.fire();_1.clearListeners();}}};var _b=function(){_1=new Ext.util.Event();if(Ext.isGecko||Ext.isOpera){document.addEventListener("DOMContentLoaded",_a,false);}else{if(Ext.isIE){document.write("<s"+"cript id=\"ie-deferred-loader\" defer=\"defer\" src=\"/"+"/:\"></s"+"cript>");var _c=document.getElementById("ie-deferred-loader");_c.onreadystatechange=function(){if(this.readyState=="complete"){_a();_c.onreadystatechange=null;_c.parentNode.removeChild(_c);}};}else{if(Ext.isSafari){_2=setInterval(function(){var rs=document.readyState;if(rs=="complete"){_a();}},10);}}}E.on(window,"load",_a);};var _e=function(h,o){var _11=new Ext.util.DelayedTask(h);return function(e){e=new Ext.EventObjectImpl(e);_11.delay(o.buffer,h,null,[e]);};};var _13=function(h,el,_16,fn){return function(e){Ext.EventManager.removeListener(el,_16,fn);h(e);};};var _19=function(h,o){return function(e){e=new Ext.EventObjectImpl(e);setTimeout(function(){h(e);},o.delay||10);};};var _1d=function(_1e,_1f,opt,fn,_22){var o=(!opt||typeof opt=="boolean")?{}:opt;fn=fn||o.fn;_22=_22||o.scope;var el=Ext.getDom(_1e);if(!el){throw"Error listening for \""+_1f+"\". Element \""+_1e+"\" doesn't exist.";}var h=function(e){e=Ext.EventObject.setEvent(e);var t;if(o.delegate){t=e.getTarget(o.delegate,el);if(!t){return;}}else{t=e.target;}if(o.stopEvent===true){e.stopEvent();}if(o.preventDefault===true){e.preventDefault();}if(o.stopPropagation===true){e.stopPropagation();}if(o.normalized===false){e=e.browserEvent;}fn.call(_22||el,e,t,o);};if(o.delay){h=_19(h,o);}if(o.single){h=_13(h,el,_1f,fn);}if(o.buffer){h=_e(h,o);}fn._handlers=fn._handlers||[];fn._handlers.push([Ext.id(el),_1f,h]);E.on(el,_1f,h);if(_1f=="mousewheel"&&el.addEventListener){el.addEventListener("DOMMouseScroll",h,false);E.on(window,"unload",function(){el.removeEventListener("DOMMouseScroll",h,false);});}if(_1f=="mousedown"&&el==document){Ext.EventManager.stoppedMouseDownEvent.addListener(h);}return h;};var _28=function(el,_2a,fn){var id=Ext.id(el),hds=fn._handlers,hd=fn;if(hds){for(var i=0,len=hds.length;i<len;i++){var h=hds[i];if(h[0]==id&&h[1]==_2a){hd=h[2];hds.splice(i,1);break;}}}E.un(el,_2a,hd);el=Ext.getDom(el);if(_2a=="mousewheel"&&el.addEventListener){el.removeEventListener("DOMMouseScroll",hd,false);}if(_2a=="mousedown"&&el==document){Ext.EventManager.stoppedMouseDownEvent.removeListener(hd);}};var _32=/^(?:scope|delay|buffer|single|stopEvent|preventDefault|stopPropagation|normalized|args|delegate)$/;var pub={wrap:function(fn,_35,_36){return function(e){Ext.EventObject.setEvent(e);fn.call(_36?_35||window:window,Ext.EventObject,_35);};},addListener:function(_38,_39,fn,_3b,_3c){if(typeof _39=="object"){var o=_39;for(var e in o){if(_32.test(e)){continue;}if(typeof o[e]=="function"){_1d(_38,e,o,o[e],o.scope);}else{_1d(_38,e,o[e]);}}return;}return _1d(_38,_39,_3c,fn,_3b);},removeListener:function(_3f,_40,fn){return _28(_3f,_40,fn);},onDocumentReady:function(fn,_43,_44){if(_3){fn.call(_43||window,_43);return;}if(!_1){_b();}_1.addListener(fn,_43,_44);},onWindowResize:function(fn,_46,_47){if(!_4){_4=new Ext.util.Event();_5=new Ext.util.DelayedTask(function(){_4.fire(D.getViewWidth(),D.getViewHeight());});E.on(window,"resize",function(){if(Ext.isIE){_5.delay(50);}else{_4.fire(D.getViewWidth(),D.getViewHeight());}});}_4.addListener(fn,_46,_47);},onTextResize:function(fn,_49,_4a){if(!_6){_6=new Ext.util.Event();var _4b=new Ext.Element(document.createElement("div"));_4b.dom.className="x-text-resize";_4b.dom.innerHTML="X";_4b.appendTo(document.body);_7=_4b.dom.offsetHeight;setInterval(function(){if(_4b.dom.offsetHeight!=_7){_6.fire(_7,_7=_4b.dom.offsetHeight);}},this.textResizeInterval);}_6.addListener(fn,_49,_4a);},removeResizeListener:function(fn,_4d){if(_4){_4.removeListener(fn,_4d);}},fireResize:function(){if(_4){_4.fire(D.getViewWidth(),D.getViewHeight());}},ieDeferSrc:false,textResizeInterval:50};pub.on=pub.addListener;pub.un=pub.removeListener;pub.stoppedMouseDownEvent=new Ext.util.Event();return pub;}();Ext.onReady=Ext.EventManager.onDocumentReady;Ext.onReady(function(){var bd=Ext.get(document.body);if(!bd){return;}var cls=[Ext.isIE?"ext-ie":Ext.isGecko?"ext-gecko":Ext.isOpera?"ext-opera":Ext.isSafari?"ext-safari":""];if(Ext.isMac){cls.push("ext-mac");}if(Ext.isLinux){cls.push("ext-linux");}if(Ext.isBorderBox){cls.push("ext-border-box");}if(Ext.isStrict){var p=bd.dom.parentNode;if(p){p.className=p.className?" ext-strict":"ext-strict";}}bd.addClass(cls.join(" "));});Ext.EventObject=function(){var E=Ext.lib.Event;var _52={63234:37,63235:39,63232:38,63233:40,63276:33,63277:34,63272:46,63273:36,63275:35};var _53=Ext.isIE?{1:0,4:1,2:2}:(Ext.isSafari?{1:0,2:1,3:2}:{0:0,1:1,2:2});Ext.EventObjectImpl=function(e){if(e){this.setEvent(e.browserEvent||e);}};Ext.EventObjectImpl.prototype={browserEvent:null,button:-1,shiftKey:false,ctrlKey:false,altKey:false,BACKSPACE:8,TAB:9,RETURN:13,ENTER:13,SHIFT:16,CONTROL:17,ESC:27,SPACE:32,PAGEUP:33,PAGEDOWN:34,END:35,HOME:36,LEFT:37,UP:38,RIGHT:39,DOWN:40,DELETE:46,F5:116,setEvent:function(e){if(e==this||(e&&e.browserEvent)){return e;}this.browserEvent=e;if(e){this.button=e.button?_53[e.button]:(e.which?e.which-1:-1);if(e.type=="click"&&this.button==-1){this.button=0;}this.type=e.type;this.shiftKey=e.shiftKey;this.ctrlKey=e.ctrlKey||e.metaKey;this.altKey=e.altKey;this.keyCode=e.keyCode;this.charCode=e.charCode;this.target=E.getTarget(e);this.xy=E.getXY(e);}else{this.button=-1;this.shiftKey=false;this.ctrlKey=false;this.altKey=false;this.keyCode=0;this.charCode=0;this.target=null;this.xy=[0,0];}return this;},stopEvent:function(){if(this.browserEvent){if(this.browserEvent.type=="mousedown"){Ext.EventManager.stoppedMouseDownEvent.fire(this);}E.stopEvent(this.browserEvent);}},preventDefault:function(){if(this.browserEvent){E.preventDefault(this.browserEvent);}},isNavKeyPress:function(){var k=this.keyCode;k=Ext.isSafari?(_52[k]||k):k;return(k>=33&&k<=40)||k==this.RETURN||k==this.TAB||k==this.ESC;},isSpecialKey:function(){var k=this.keyCode;return(this.type=="keypress"&&this.ctrlKey)||k==9||k==13||k==40||k==27||(k==16)||(k==17)||(k>=18&&k<=20)||(k>=33&&k<=35)||(k>=36&&k<=39)||(k>=44&&k<=45);},stopPropagation:function(){if(this.browserEvent){if(this.type=="mousedown"){Ext.EventManager.stoppedMouseDownEvent.fire(this);}E.stopPropagation(this.browserEvent);}},getCharCode:function(){return this.charCode||this.keyCode;},getKey:function(){var k=this.keyCode||this.charCode;return Ext.isSafari?(_52[k]||k):k;},getPageX:function(){return this.xy[0];},getPageY:function(){return this.xy[1];},getTime:function(){if(this.browserEvent){return E.getTime(this.browserEvent);}return null;},getXY:function(){return this.xy;},getTarget:function(_59,_5a,_5b){return _59?Ext.fly(this.target).findParent(_59,_5a,_5b):this.target;},getRelatedTarget:function(){if(this.browserEvent){return E.getRelatedTarget(this.browserEvent);}return null;},getWheelDelta:function(){var e=this.browserEvent;var _5d=0;if(e.wheelDelta){_5d=e.wheelDelta/120;if(window.opera){_5d=-_5d;}}else{if(e.detail){_5d=-e.detail/3;}}return _5d;},hasModifier:function(){return!!((this.ctrlKey||this.altKey)||this.shiftKey);},within:function(el,_5f){var t=this[_5f?"getRelatedTarget":"getTarget"]();return t&&Ext.fly(el).contains(t);},getPoint:function(){return new Ext.lib.Point(this.xy[0],this.xy[1]);}};return new Ext.EventObjectImpl();}();