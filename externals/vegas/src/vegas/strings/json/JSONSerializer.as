/*

  Version: MPL 1.1/GPL 2.0/LGPL 2.1
 
  The contents of this file are subject to the Mozilla Public License Version
  1.1 (the "License"); you may not use this file except in compliance with
  the License. You may obtain a copy of the License at 
  
           http://www.mozilla.org/MPL/ 
  
  Software distributed under the License is distributed on an "AS IS" basis,
  WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
  for the specific language governing rights and limitations under the License. 
  
  The Original Code is VEGAS Framework.
  
  The Initial Developer of the Original Code is
  ALCARAZ Marc (aka eKameleon)  <ekameleon@gmail.com>.
  Portions created by the Initial Developer are Copyright (C) 2004-2011
  the Initial Developer. All Rights Reserved.
  
  Contributor(s) :
  
  Alternatively, the contents of this file may be used under the terms of
  either the GNU General Public License Version 2 or later (the "GPL"), or
  the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
  in which case the provisions of the GPL or the LGPL are applicable instead
  of those above. If you wish to allow use of your version of this file only
  under the terms of either the GPL or the LGPL, and not to allow others to
  use your version of this file under the terms of the MPL, indicate your
  decision by deleting the provisions above and replace them with the notice
  and other provisions required by the LGPL or the GPL. If you do not delete
  the provisions above, a recipient may use your version of this file under
  the terms of any one of the MPL, the GPL or the LGPL.
  
*/

package vegas.strings.json 
{
    import system.Serializer;
    
    /**
     * This class is the concrete class of the JSON singleton.
     * <code class="prettyprint">JSON</code> (JavaScript object Notation) is a lightweight data-interchange format.
     * <p>More information in the official site : <a href="http://www.JSON.org/">http://www.JSON.org</a></p>
     * <p>Add Hexa Digits tool in deserialize method - <a href="http://code.google.com/p/edenrr/">eden inspiration</a></p>
     * <p><b>Example :</b></p>
     * <pre class="prettyprint">
     * import core.getClassName ;
     * 
     * import vegas.strings.JSON;
     * import vegas.strings.JSONError;
     * 
     * // --- Init
     * 
     * var a:Array   = [2, true, "hello"] ;
     * var o:Object  = { prop1 : 1 , prop2 : 2 } ;
     * var s:String  = "hello world" ;
     * var n:Number  = 4 ;
     * var b:Boolean = true ;
     * 
     * trace("Serialize") ;
     * 
     * trace("- a : " + JSON.serialize( a ) )  ;
     * trace("- o : " + JSON.serialize( o ) )  ;
     * trace("- s : " + JSON.serialize( s ) )  ;
     * trace("- n : " + JSON.serialize( n ) )  ;
     * trace("- b : " + JSON.serialize( b ) )  ;
     * 
     * trace ("Deserialize") ;
     * 
     * var source:String = '[ { "prop1" : 0xFF0000 , prop2:2, prop3:"hello", prop4:true} , 2, true, 3, [3, 2] ]' ;
     * 
     * o = JSON.deserialize(source) ;
     * 
     * var l:uint = o.length ;
     * for (var i:uint = 0 ; i &lt; l ; i++)
     * {
     *     trace("- " + i + " : " + o[i] + " , typeof :: " + typeof(o[i])) ;
     *     if (typeof(o[i]) == "object")
     *     {
     *         for (var each:String in o[i])
     *         {
     *             trace("    + " + each + " : " + o[i][each] + " :: " + getClassName(o[i][each]) ) ;
     *         }
     *     }
     * }
     * 
     * trace ("JSONError") ;
     * 
     * source = "[3, 2," ; // test1
     * 
     * // var source:String = '{"prop1":coucou"}' ; // test2
     * 
     * try
     * {
     *    var errorObj:Object = JSON.deserialize(source) ;
     * }
     * catch( e:JSONError )
     * {
     *     trace( e.toString() ) ;
     * }
     * </pre>
     */
    public class JSONSerializer implements Serializer 
    {
        /**
         * Creates a new JSONSerializer instance.
         */
        public function JSONSerializer()
        {
            //
        }
        
        /**
         * The source to evaluate.
         */
        public var source:String ;
        
        /**
         * Indicates the indentor string representation.
         */
        public function get indentor():String
        {
            return _indentor;
        }
        
        /**
         * @private
         */
        public function set indentor(value:String):void
        {
            _indentor = value;
        }
        
        /**
         * Indicates the pretty indent value.
         */   
        public function get prettyIndent():int
        {
            return _prettyIndent;
        }
        
        /**
         * @private
         */
        public function set prettyIndent(value:int):void
        {
             _prettyIndent = value ;
        }
        
        /**
         * Indicates the pretty printing flag value.
         */        
        public function get prettyPrinting():Boolean
        {
            return _prettyPrinting ;
        }        
        
        /**
         * @private
         */
        public function set prettyPrinting(value:Boolean):void
        {
            _prettyPrinting = value;
        }
        
        /**
         * Parse a string and interpret the source code to the correct object construct.
         * <p><b>Example :</b></p>
         * <pre class="prettyprint">
         * "hello world" --> "hello world"
         * "0xFF"        --> 255
         * "{a:1,"b":2}" --> {a:1,b:2}
         * </pre>
         * @return a string representing the data.
         */ 
        public function deserialize( source:String ):*
        {
            this.source = source ;
            at = 0 ;
            ch = ' ' ;
            return value() ;
        }
        
        /**
         * Serialize the specified value object passed-in argument.
         */
        public function serialize( value:* ):String
        {
            var c:String ; // char
            var i:int ;
            var l:int ;
            var s:String = '' ;
            var v:* ;
            var tof:String = typeof(value) ;
            switch (tof) 
            {
                case 'object' :
                {
                    if (value)
                    {
                        if (value is Array) 
                        {
                            l = (value as Array).length ;
                            for (i = 0 ; i < l ; ++i) 
                            {
                                v = serialize(value[i]);
                                if (s) s += ',' ;
                                s += v ;
                            }
                            return '[' + s + ']';
                        }
                        else if ( typeof( value.toString ) != 'undefined') 
                        {
                            for (var prop:String in value) 
                            {
                                v = value[prop];
                                if ( (typeof(v) != 'undefined') && (typeof(v) != 'function') ) 
                                {
                                    v = serialize(v);
                                    if (s) 
                                    {
                                        s += ',' ;
                                    }
                                    s += serialize(prop) + ':' + v ;
                                }
                            }
                            return "{" + s + "}";
                        }
                    }
                    return 'null';
                }
                case 'number':
                {
                    return isFinite(value) ? String(value) : 'null' ;
                }
                case 'string' :
                {
                    l = (value as String).length ;
                    s = '"' ;
                    for (i = 0 ; i < l ; i += 1) 
                    {
                        c = (value as String).charAt(i) ;
                        if (c >= ' ') 
                        {
                            if (c == '\\' || c == '"') 
                            {
                                s += '\\';
                            }
                            s += c;
                        } 
                        else 
                        {
                            switch (c) 
                            {
                                case '\b':
                                {
                                    s += '\\b';
                                    break ;
                                }
                                case '\f' :
                                {
                                    s += '\\f' ;
                                    break ;
                                }
                                case '\n' :
                                {
                                    s += '\\n' ;
                                    break ;
                                }
                                case '\r':
                                {
                                    s += '\\r' ;
                                    break ;
                                }
                                case '\t':
                                {
                                    s += '\\t' ;
                                    break ;
                                }
                                default:
                                {
                                    var code:Number = c.charCodeAt() ;
                                    s += '\\u00' + String(Math.floor(code / 16).toString(16)) + ((code % 16).toString(16)) ;
                                }
                            }
                        }
                    }
                    return s + '"' ;
                }
                case 'boolean' :
                {
                    return String(value);
                }
                default :
                {
                    return 'null';
                }
            }
        }
       
        /**
         * The current position of the iterator in the source.
         */
        protected var at:Number = 0 ;

        /**
         * The current character of the iterator in the source.
         */
        protected var ch:String = ' ' ;
        
        /**
         * Check the Array objects in the source expression.
         */
        protected function array():Array 
        {
            var a:Array = [];
            if ( ch == '[' ) 
            {
                next()  ;
                white() ;
                if (ch == ']') 
                {
                    next();
                    return a;
                }
                while (ch) 
                {
                    a.push( value() ) ;
                    white();
                    if (ch == ']') 
                    {
                        next();
                        return a;
                    }
                    else if (ch != ',') 
                    {
                       break;
                    }
                    next();
                    white();
                }
            }
            error( JSONStrings.badArray );
            return null ;
        }        
        
        /**
         * Throws a JSONError with the passed-in message.
         */
        protected function error( m:String ):void 
        {
            throw new JSONError( m, at - 1 , source) ;
        }
        
        /**
         * Indicates if the passed-in character is a digit.
         */
        protected function isDigit( c:String ):Boolean
        {
            return( ("0" <= c) && (c <= "9") );
        }
        
        /**
         * Indicates if the passed-in character is a hexadecimal digit.
         */
        protected function isHexDigit( c:String ):Boolean 
        {
            return( isDigit( c ) || (("A" <= c) && (c <= "F")) || (("a" <= c) && (c <= "f")) );
        }
        
        /**
         * Indicates if the current character is a key.
         */
        protected function key():*
        {
            var s:String        = ch ;
            var semiColon:int   = source.indexOf( ':' , at ) ;
            var quoteIndex:int  = source.indexOf( '"' , at ) ;
            var squoteIndex:int = source.indexOf( "'" , at ) ;
            if( (quoteIndex <= semiColon && quoteIndex > -1) || (squoteIndex <= semiColon && squoteIndex > -1))
            {
                s = string() ;
                white() ;
                if(ch == ':')
                {
                    return s;
                }
                else
                {
                    error(JSONStrings.badKey);
                }
            }
            while ( next() ) // Use key handling 
            {
                if (ch == ':') 
                {
                    return s;
                } 
                if(ch <= ' ')
                {
                    //
                }
                else
                {
                    s += ch;
                }
            }
            error( JSONStrings.badKey ) ;
        }
        
        /**
         * Returns the next character in the source String representation.
         * @return the next character in the source String representation.
         */
        protected function next():String
        {
           ch = source.charAt(at);
           at += 1;
           return ch;
        }
        
        /**
         * Check the Number values in the source expression.
         */    
        protected function number():* 
        {
            
            var n:* = '' ;
            var v:* ;
            var hex:String = '' ;
            var sign:String = '' ;
            if (ch == '-') 
            {
                n = '-';
                sign = n ;
                next();
            }
            if( ch == "0" ) 
            {
                next() ;
                if( ( ch == "x") || ( ch == "X") ) 
                {
                    next();
                    while( isHexDigit( ch ) ) 
                    {
                        hex += ch ;
                        next();
                    }
                    if( hex == "" ) 
                    {
                        error(JSONStrings.malFormedHexadecimal) ;
                    }
                    else 
                    {
                        return Number( sign + "0x" + hex ) ;
                    }
                } 
                else 
                {
                    n += "0" ;
                }
            }
            while ( isDigit(ch) ) 
            {
                n += ch ;
                next() ;
            }
            if (ch == '.') 
            {
                n += '.';
                while (next() && ch >= '0' && ch <= '9') 
                {
                    n += ch ;
                }
            }
            v = 1 * n ;
            if (!isFinite(v)) 
            {
                error( JSONStrings.badNumber );
            }
            else 
            {
                return v ;
            }
            return NaN ;
        }        
        
        /**
         * Check the Object values in the source expression.
         */       
        protected function object():* 
        {
            var k:* = {} ;
            var o:* = {} ;
            if (ch == '{') 
            {
                next();
                white();
                if (ch == '}') 
                {
                    next() ;
                    return o ;
                }
                while (ch) 
                {
                    k = key() ;
                    white();
                    if (ch != ':') 
                    {
                        break;
                    }
                    next();
                    o[k] = value() ;
                    white();
                    if (ch == '}') 
                    {
                        next();
                        return o;
                    } 
                    else if (ch != ',') 
                    {
                        break;
                    }
                    next();
                    white();
                }
            }
            error( JSONStrings.badObject ) ;
        }
        
        /**
         * Check the string objects in the source expression.
         */
        protected function string():* 
        {
           var i:* = '' ;
           var s:* = '' ; 
           var t:* ;
           var u:* ;
           var outer:Boolean ;
           if (ch == '"' || ch == "'" ) 
           {
               var outerChar:String = ch ;
               while ( next() ) 
               {
                   if (ch == outerChar) 
                   {
                        next() ;
                        return s ;
                   }
                   else if (ch == '\\') 
                   {
                        switch ( next() ) 
                        {
                            case 'b':
                            {
                                s += '\b' ;
                                break ;
                            }
                            case 'f' :
                            {
                                s += '\f';
                                break ;
                            }
                            case 'n':
                            {
                                s += '\n';
                                break ;
                            }
                            case 'r' :
                            {
                                s += '\r';
                                break ;
                            }
                            case 't' :
                            {
                                s += '\t' ;
                                break ;
                            }
                            case 'u' :
                            {
                                u = 0;
                                for (i = 0; i < 4; i += 1) 
                                {
                                    t = parseInt( next() , 16 ) ;
                                    if (!isFinite(t)) 
                                    {
                                        outer = true;
                                        break;
                                    }
                                    u = u * 16 + t;
                                }
                                if(outer) 
                                {
                                    outer = false;
                                    break;
                                }
                                s += String.fromCharCode(u);
                                break;
                            }
                            default :
                            {
                                s += ch;
                            }
                        }
                    } 
                    else 
                    {
                        s += ch;
                    }
                }
            }
            error( JSONStrings.badString );
            return null ;
        }
        
        /**
         * Evaluates the values in the source expression.
         */
        protected function value():* 
        {
            white() ;
            if (ch == '{' ) 
            {
                return object();
            }
            else if ( ch == '[' )
            {
                return array();
            }
            else if ( ch == '"' || ch == "'" )
            {
                return string();
            }
            else if ( ch == '-' ) 
            {
                return number();
            }
            else 
            {
                return ( ch >= '0' && ch <= '9' ) ? number() : word() ;
            }
        }        
        
        /**
         * Check all white spaces.
         */
        protected function white():void 
        {
            while (ch) 
            {
                if (ch <= ' ') 
                {
                    next();
                } 
                else if (ch == '/') 
                {
                    switch ( next() ) 
                    {
                        case '/' :
                        {
                            while ( next() && ch != '\n' && ch != '\r') 
                            {
                            }
                            break;
                        }
                        case '*' :
                        {
                            next();
                            for (;;) 
                            {
                                if (ch) 
                                {
                                    if (ch == '*') 
                                    {
                                        if ( next() == '/' ) 
                                        {
                                            next();
                                            break;
                                        }
                                    } 
                                    else 
                                    {
                                        next();
                                    }
                                }
                                else 
                                {
                                    error( JSONStrings.unterminatedComment );
                                }
                            }
                            break ;
                        }
                        default :
                        {
                            error( JSONStrings.syntaxError );
                        }
                    }
                } 
                else 
                {
                    break ;
                }
            }
        }
        
        /**
         * Check all special words in the source to evaluate.
         */
        protected function word():* 
        {
            if (ch == 't') 
            {
                if (next() == 'r' && next() == 'u' && next() == 'e') 
                {
                    next() ;
                    return true ;
                }
            }
            else if ( ch == 'f' )
            {
                if (next() == 'a' && next() == 'l' && next() == 's' && next() == 'e') 
                {
                    next() ;
                    return false ;
                }
            }
            else if ( ch == 'n' )
            {
                if (next() == 'u' && next() == 'l' && next() == 'l') 
                {
                    next() ;
                    return null ;
                }
            }
            error( JSONStrings.syntaxError );
            return null ;
        }
        
        /**
         * @private
         */
        private var _prettyIndent:int = 0 ;
        
        /**
         * @private
         */
        private var _prettyPrinting:Boolean ;
        
        /**
         * @private
         */
        private var _indentor:String = "    " ;
    }
}
