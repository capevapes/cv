/**
 * Belvg_Verpage - Copyright (c) 2010 - 2014 BelVG LLC. (http://www.belvg.com)
 */
var verpage = {
    age : 0,
    cookieLifetime : 0,
    rimageWidth : 0,
    rimageHeight : 0,
    setCookie : function(name, value, path, domain, secure) {
        if (!name || !value) return false;
        var str = name + '=' + encodeURIComponent(value);
        if (path)    str += '; path=' + path;
        if (domain)  str += '; domain=' + domain;
        if (secure)  str += '; secure';
        if (this.cookieLifetime) {
            var date = new Date( new Date().getTime() + this.cookieLifetime*1000);
            str += '; expires=' + date.toUTCString();
        }
        document.cookie = str;
        return true;
    },
    getCookie : function(name) {
        var pattern = "(?:; )?" + name + "=([^;]*);?";
        var regexp  = new RegExp(pattern);
        
        if (regexp.test(document.cookie))
        return decodeURIComponent(RegExp["$1"]);
        
        return false;
    },
    isValid : function() {
        if (this.getCookie('verpage_valid')) return true;
        return false;
    },
    setValid : function() {
        this.setCookie('verpage_valid', 1, Mage.Cookies.path, Mage.Cookies.domain);
    },
    checkEnterButton : function() {
        here = this;
        var curDate = new Date();
        var flag = true;
        if (jQblvg('.verpage_select select').length) {
            var birth = [];
            jQblvg('.verpage_select select').each( function(i){
                birth[i] = jQblvg(this).val();
            });
            var birthDate = new Date(parseInt(birth[2])+parseInt(here.age), birth[1]-1, birth[0]);
        };
        if (typeof(birthDate) == 'object') {
            flag = (curDate.getTime() > birthDate.getTime())?true:false;
        };
        if (jQblvg('#verpage_confirm').length && flag) {
            if ((jQblvg('#verpage_confirm').attr('checked') != 'checked') || !flag) {
                flag = false;
            }
        };
        if (flag) {
            here.activateEnterButton()
        } else {
            jQblvg('.verpage_actions .enter').addClass('disabled').unbind('click')
        }
    },
    activateEnterButton : function() {
        here = this;
        jQblvg('.verpage_actions .enter').removeClass('disabled');
        jQblvg('.verpage_actions .enter').click( function(){
            here.setValid();
            jQblvg.nmTop().close();
        });
    },
    resizeImage : function() {
        if (this.rimageWidth*3 > jQblvg(document).width()) {
            jQblvg('.verpage_logo img').attr('width', this.rimageWidth / 2);
            jQblvg('.verpage_logo img').attr('height', this.rimageHeight / 2);
        } else {
            jQblvg('.verpage_logo img').attr('width', this.rimageWidth);
            jQblvg('.verpage_logo img').attr('height', this.rimageHeight);
        }
    }
};