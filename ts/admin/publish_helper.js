//
// publish_helper.js
//
// Copyright John McAleely 2008


var worklist = new Array();
var make_torrent_url;
var make_torrent_post_options;

function helper_onLoad() { 
	create_worklist(); 

	request_next_torrent();
}


function add_workitem(file, id) {
	item = new Object();
	item.filename = file;
	item.statusid = id;

	worklist.push(item);
}


function next_workitem(hook, request) {

	if (request.readyState != 4) return;

	var link = document.getElementById(hook.statusid);

	if (request.status != 200 && request.status != 304) {
		link.firstChild.nodeValue='http error: ' + request.status;
	}
	else {
		link.firstChild.nodeValue='torrent ready';
	}

	request_next_torrent();
}


function request_next_torrent() {
	var item = worklist.pop();
	if (item) {
		var query = make_torrent_url+'&filename='+item.filename;
		var link = document.getElementById(item.statusid);

		link.firstChild.nodeValue='torrent building';

		if (!sendRequest(query, next_workitem, item, make_torrent_post_options)) {
			var message = document.getElementById('message');
			message.firstChild.nodeValue = 'Error: could not request torrent';
		}
	}
}


// originally from http://www.quirksmode.org/js/xmlhttp.html
function sendRequest(url,callback,hook,postData) {
	var req = createXMLHTTPObject();
	if (!req) return false;
	var method = (postData) ? "POST" : "HEAD";
	req.open(method,url,true);
	req.setRequestHeader('User-Agent','XMLHTTP/1.0');
	if (postData)
		req.setRequestHeader('Content-type','application/x-www-form-urlencoded');
	req.onreadystatechange = function () { callback(hook, req); }
	if (req.readyState == 4) return false;
	req.send(postData);
	return true;
}

// from http://www.quirksmode.org/js/xmlhttp.html
var XMLHttpFactories = [
	function () {return new XMLHttpRequest()},
	function () {return new ActiveXObject("Msxml2.XMLHTTP")},
	function () {return new ActiveXObject("Msxml3.XMLHTTP")},
	function () {return new ActiveXObject("Microsoft.XMLHTTP")}
];

// from http://www.quirksmode.org/js/xmlhttp.html
function createXMLHTTPObject() {
	var xmlhttp = false;
	for (var i=0;i<XMLHttpFactories.length;i++) {
		try {
			xmlhttp = XMLHttpFactories[i]();
		}
		catch (e) {
			continue;
		}
		break;
	}
	return xmlhttp;
}
