/***********************************************
 * Cool DHTML tooltip script II- © Dynamic Drive DHTML code library (www.dynamicdrive.com)
 * This notice MUST stay intact for legal use
 * Visit Dynamic Drive at http://www.dynamicdrive.com/ for full source code
 ***********************************************/
var offsetxpoint = -60; //Customize x offset of tooltip
var offsetypoint = 20; //Customize y offset of tooltip
var ie = document.all;
var ns6 = document.getElementById && !document.all;
var enabletip = false;
var tipobj = false;

function jsInit() {
    if (ie || ns6) {
        tipobj = document.all ? document.all.dhtmltooltip : document.getElementById ? document.getElementById("dhtmltooltip") : "";
        //alert(tipobj);
    }
}

var offsetfromcursorX = 12; //Customize x offset of tooltip
var offsetfromcursorY = 10; //Customize y offset of tooltip

var offsetdivfrompointerX = 10; //Customize x offset of tooltip DIV relative to pointer image
var offsetdivfrompointerY = 14; //Customize y offset of tooltip DIV relative to pointer image. Tip: Set it to (height_of_pointer_image-1).

document.write('<div id="dhtmltooltip"></div>'); //write out tooltip DIV
document.write('<img id="dhtmlpointer" src="arrow.gif">'); //write out pointer image

if (ie || ns6) {
    var tipobj = document.all ? document.all.dhtmltooltip : document.getElementById ? document.getElementById("dhtmltooltip") : "";
}

var pointerobj = document.all ? document.all.dhtmlpointer : document.getElementById ? document.getElementById("dhtmlpointer") : "";

function ietruebody() {
    return (document.compatMode && document.compatMode !== "BackCompat") ? document.documentElement : document.body;
}

function ddrivetip(thetext, thewidth, thecolor) {
    if (!tipobj) {
        return false;
    }
    if (ns6 || ie) {
        if (thewidth !== undefined) {
            tipobj.style.width = thewidth + "px";
        }
        if (thecolor !== undefined && thecolor !== "") {
            tipobj.style.backgroundColor = thecolor;
        }
        tipobj.innerHTML = thetext;
        enabletip = true;
        return false;
    }
}

function positiontip(e) {
    if (enabletip) {
        var nondefaultpos = false;
        var curX = (ns6) ? e.pageX : event.clientX + ietruebody().scrollLeft;
        var curY = (ns6) ? e.pageY : event.clientY + ietruebody().scrollTop;
        //Find out how close the mouse is to the corner of the window
        var winwidth = (ie && !window.opera) ? ietruebody().clientWidth : window.innerWidth - 20;
        var winheight = (ie && !window.opera) ? ietruebody().clientHeight : window.innerHeight - 20;

        var rightedge = (ie && !window.opera) ? winwidth - event.clientX - offsetfromcursorX : winwidth - e.clientX - offsetfromcursorX;
        var bottomedge = (ie && !window.opera) ? winheight - event.clientY - offsetfromcursorY : winheight - e.clientY - offsetfromcursorY;

        var leftedge = (offsetfromcursorX < 0) ? offsetfromcursorX * (-1) : -1000;

        if (curX < leftedge) {
            tipobj.style.left = "5px";
        } else {
            //position the horizontal position of the menu where the mouse is positioned
            tipobj.style.left = curX + offsetfromcursorX - offsetdivfrompointerX + "px";
            pointerobj.style.left = curX + offsetfromcursorX + "px";
        }

        //same concept with the vertical position
        if (bottomedge < tipobj.offsetHeight) {
            tipobj.style.top = curY - tipobj.offsetHeight - offsetfromcursorY + "px";
            nondefaultpos = true;
        } else {
            tipobj.style.top = curY + offsetfromcursorY + offsetdivfrompointerY + "px";
            pointerobj.style.top = curY + offsetfromcursorY + "px";
        }
        tipobj.style.visibility = "visible";
        if (!nondefaultpos) {
            pointerobj.style.visibility = "visible";
        } else {
            pointerobj.style.visibility = "hidden";
        }
    }
}

function hideddrivetip() {
    if (ns6 || ie) {
        enabletip = false;
        tipobj.style.visibility = "hidden";
        pointerobj.style.visibility = "hidden";
        tipobj.style.left = "-1000px";
        tipobj.style.backgroundColor = '';
        tipobj.style.width = '';
    }
}

document.onmousemove = positiontip;
