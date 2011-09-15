// Global scope
var ApiInterface;

(function () {

  'use strict';

  var Ajax, toQueryString, populateControllers, isFunction, isString, indexOf, randomInteger;

  indexOf = Array.prototype.indexOf; // TODO - old browsers fallback

  isFunction = function (o) {
    return typeof o === 'function';
  };

  isString = function (o) {
    return typeof o === 'string';
  };

  // Changes the object into query string
  // eg. {foo:bar, lorem:ipsum} becomes 'foo=bar&lorem=ipsum'
  toQueryString = function (obj) {

    var key, str = '';
  
    if (obj) {
      for (key in obj) {
        if (obj.hasOwnProperty(key)) {
          str += (str === '' ? '' : '&') +
            encodeURIComponent(key) + '=' + encodeURIComponent(obj[key]);
        }
      }
    }
  
    return str;
  
  };

  // Random integer between min and max (inclusive)
  randomInteger = function (min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
  };

  Ajax = function (options) {

    options = options || {};

    this.path = options.path;

    this.method = options.method || 'POST';

    this.success = options.success;

    this.error = options.error;

    this.complete = options.complete;

    this.data = toQueryString(options.data);

    this.parse_json = typeof options.parse_json !== 'undefined' ? !!options.parse_json : true;

  };

  Ajax.prototype = {

    constructor: Ajax,

    getAjaxRequest: function () {

      var p = Ajax.prototype;

      p.getAjaxRequest = function () { return new XMLHttpRequest(); };
      try { return this.getAjaxRequest(); } catch (e) {}

      p.getAjaxRequest = function () { return new ActiveXObject("Msxml2.XMLHTTP.6.0"); };
      try { return this.getAjaxRequest(); } catch (e) {}

      p.getAjaxRequest = function () { return new ActiveXObject("Msxml2.XMLHTTP.3.0"); };
      try { return this.getAjaxRequest(); } catch (e) {}

      p.getAjaxRequest = function () { return new ActiveXObject("Msxml2.XMLHTTP"); };
      try { return this.getAjaxRequest(); } catch (e) {}

      p.getAjaxRequest = function () { return new ActiveXObject("Microsoft.XMLHTTP"); };
      try { return this.getAjaxRequest(); } catch (e) {}

      return (p.getAjaxRequest = function () { return null; })();

    },

    send: function () {

      var that = this;

      if (!this.path) {
        throw 'No path provided to Ajax';
      }

      this.request = this.getAjaxRequest();
      this.request.open(this.method, this.path, true);
      this.request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

      this.request.onreadystatechange = function () {
        if (that.request.readyState === 4) {
          if (that.request.status === 200 && isFunction(that.success)) {
            if (that.parse_json) {
              that.request.responseObject = JSON.parse(that.request.responseText);
            }
            that.success.call(that);
          } else if (that.request.status !== 200 && isFunction(that.error)) {
            that.error.call(that);
          }
          if (isFunction(that.complete)) {
            that.complete.call(that);
          }
          delete that.request;
        }
      };

      if (this.data) {
        this.request.setRequestHeader('Content-length', this.data.length);
      }

      this.request.send(this.data);

    }

  };

  ApiInterface = function (path, interval) {

    this.path = path;

    this.timer_interval = interval || 10000; // 10 seconds by default

    this.controllers = {};

    this.callbacks = {ready: [], ajax_queue: []};

    this.isReady = false;

    populateControllers.call(this);

  };

  populateControllers = function () {
    var that = this;
    this.call({
      controller: 'main',
      action: 'getControllers',
      success: function (controllers) {
        var controller_details, controller, i, l, j, k, action;

        for (i = 0, l = controllers.length; i < l; i += 1) {
          controller_details = controllers[i];
          controller = function () {
            // TODO
          };
          controller.prototype = {
            constructor: controller,
            _delegate: that,
            _name: controller_details.name
          };
          for (j = 0, k = controller_details.actions.length; j < k; j += 1) {
            action = controller_details.actions[j];
            controller.prototype[action] = function (name) {
              return function (args, success, error, complete) {
                this._delegate.call({
                  controller: this._name,
                  action: name,
                  args: args,
                  success: success,
                  error: error,
                  complete: complete
                });
              };
            }(action);
          }
          that.controllers[controller_details.class] = controller;
        }
        controller = controller_details = action = null;

        that.isReady = true;

        for (i = 0, l = that.callbacks.ready.length; i < l; i += 1) {
          that.callbacks.ready[i].call(that);
        }
        that.callbacks.ready = [];
        that.processQueue();
      }
    });
  };

  ApiInterface.prototype = {

    constructor: ApiInterface,

    processQueue: function () {
      var that = this, callbacks, callback, requests, label, labels, i, l;

      if (this.callbacks.ajax_queue.length < 1 || this.ajax) {
        return;
      }

      callbacks = this.callbacks.ajax_queue;
      this.callbacks.ajax_queue = [];

      labels = {};
      requests = [];

      for (i = 0, l = callbacks.length; i < l; i += 1) {
        callback = callbacks[i];

        do {
          label = String(randomInteger(100000, 999999));
        } while (labels.hasOwnProperty(label));

        labels[label] = callback;
        callback.label = label;
        requests.push({
          controller: callback.controller,
          action: callback.action,
          args: callback.args,
          label: callback.label
        });
      }

      callback = label = i = l = null;

      requests = JSON.stringify(requests);

      this.ajax = new Ajax({
        path: this.path,
        method: 'POST',
        data: {requests: requests},
        success: function () {
          var response, callback, i, l, data, status, message, context;

          response = this.request.responseObject;

          if (!response || !response.status || response.status !== 200) {
            if (isFunction(this.error)) {
              this.error.call(this);
            }
            return;
          }

          for (i = 0, l = response.results.length; i < l; i += 1) {
            data = response.results[i];
            if (!data) {
              continue;
            }

            callback = labels[data.label];
            status = data.status || 500;
            message = data.error || '';
            context = callback.context || this;

            if (status === 200 && isFunction(callback.success)) {
              callback.success.call(callback.context, data.result, this.request);
            } else if (status !== 200 && isFunction(callback.error)) {
              callback.error.call(callback.context, status, message, this.request);
            }

            callback = data = status = message = null;
          }
        },
        error: function () {
          var i, l, callback, status = this.request.status, message = '';

          if (status === 200) {
            if (!response || !response.status) {
              status = 500;
            } else {
              status = response.status;
              message = response.error || '';
            }
          }

          for (i = 0, l = callbacks.length; i < l; i += 1) {
            callback = callbacks[i];
            if (isFunction(callback.error)) {
              callback.error.call(callback.context, status, message, this.request);
            }
          }
        },
        complete: function () {
          delete that.ajax;
          that.processQueue();
        }
      });
      this.ajax.send();
    },

    // TODO - ability to override previous call thats still in queue
    call: function (options) {
      options.context = options.context || this;
      this.callbacks.ajax_queue.push(options);
      this.processQueue();
      return this;
    },

    ready: function (callback) {
      if (this.isReady) {
        callback.call(this);
      } else {
        this.callbacks.ready.push(callback);
      }
      return this;
    }

  };

}());

var JSON;if(!JSON){JSON={};}(function(){"use strict";function f(n){return n<10?'0'+n:n;}if(typeof Date.prototype.toJSON!=='function'){Date.prototype.toJSON=function(key){return isFinite(this.valueOf())?this.getUTCFullYear()+'-'+f(this.getUTCMonth()+1)+'-'+f(this.getUTCDate())+'T'+f(this.getUTCHours())+':'+f(this.getUTCMinutes())+':'+f(this.getUTCSeconds())+'Z':null;};String.prototype.toJSON=Number.prototype.toJSON=Boolean.prototype.toJSON=function(key){return this.valueOf();};}var cx=/[\u0000\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g,escapable=/[\\\"\x00-\x1f\x7f-\x9f\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g,gap,indent,meta={'\b':'\\b','\t':'\\t','\n':'\\n','\f':'\\f','\r':'\\r','"':'\\"','\\':'\\\\'},rep;function quote(string){escapable.lastIndex=0;return escapable.test(string)?'"'+string.replace(escapable,function(a){var c=meta[a];return typeof c==='string'?c:'\\u'+('0000'+a.charCodeAt(0).toString(16)).slice(-4);})+'"':'"'+string+'"';}function str(key,holder){var i,k,v,length,mind=gap,partial,value=holder[key];if(value&&typeof value==='object'&&typeof value.toJSON==='function'){value=value.toJSON(key);}if(typeof rep==='function'){value=rep.call(holder,key,value);}switch(typeof value){case'string':return quote(value);case'number':return isFinite(value)?String(value):'null';case'boolean':case'null':return String(value);case'object':if(!value){return'null';}gap+=indent;partial=[];if(Object.prototype.toString.apply(value)==='[object Array]'){length=value.length;for(i=0;i<length;i+=1){partial[i]=str(i,value)||'null';}v=partial.length===0?'[]':gap?'[\n'+gap+partial.join(',\n'+gap)+'\n'+mind+']':'['+partial.join(',')+']';gap=mind;return v;}if(rep&&typeof rep==='object'){length=rep.length;for(i=0;i<length;i+=1){if(typeof rep[i]==='string'){k=rep[i];v=str(k,value);if(v){partial.push(quote(k)+(gap?': ':':')+v);}}}}else{for(k in value){if(Object.prototype.hasOwnProperty.call(value,k)){v=str(k,value);if(v){partial.push(quote(k)+(gap?': ':':')+v);}}}}v=partial.length===0?'{}':gap?'{\n'+gap+partial.join(',\n'+gap)+'\n'+mind+'}':'{'+partial.join(',')+'}';gap=mind;return v;}}if(typeof JSON.stringify!=='function'){JSON.stringify=function(value,replacer,space){var i;gap='';indent='';if(typeof space==='number'){for(i=0;i<space;i+=1){indent+=' ';}}else if(typeof space==='string'){indent=space;}rep=replacer;if(replacer&&typeof replacer!=='function'&&(typeof replacer!=='object'||typeof replacer.length!=='number')){throw new Error('JSON.stringify');}return str('',{'':value});};}if(typeof JSON.parse!=='function'){JSON.parse=function(text,reviver){var j;function walk(holder,key){var k,v,value=holder[key];if(value&&typeof value==='object'){for(k in value){if(Object.prototype.hasOwnProperty.call(value,k)){v=walk(value,k);if(v!==undefined){value[k]=v;}else{delete value[k];}}}}return reviver.call(holder,key,value);}text=String(text);cx.lastIndex=0;if(cx.test(text)){text=text.replace(cx,function(a){return'\\u'+('0000'+a.charCodeAt(0).toString(16)).slice(-4);});}if(/^[\],:{}\s]*$/.test(text.replace(/\\(?:["\\\/bfnrt]|u[0-9a-fA-F]{4})/g,'@').replace(/"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g,']').replace(/(?:^|:|,)(?:\s*\[)+/g,''))){j=eval('('+text+')');return typeof reviver==='function'?walk({'':j},''):j;}throw new SyntaxError('JSON.parse');};}}());
