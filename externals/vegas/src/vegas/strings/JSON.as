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
package vegas.strings
{
    import vegas.strings.json.JSONSerializer;
    
    /**
     * <code class="prettyprint">JSON</code> (JavaScript object Notation) is a lightweight data-interchange format.
     * <p>More information in the official site : <a href="http://www.JSON.org/">http://www.JSON.org</a></p>
     * <p>Add Hexa Digits tool in deserialize method - <a href="http://code.google.com/p/edenrr/">eden inspiration</a></p>
     * 
     * @example Example
     * <listing version="3.0">
     * <code class="prettyprint">
     * import core.getClassName ;
     * 
     * import vegas.strings.JSON;
     * import vegas.strings.errors.JSONError;
     * 
     * // --- Init
     * 
     * var a:Array   = [2, true, "hello"] ;
     * var o:Object  = { prop1 : 1 , prop2 : 2 } ;
     * var s:String  = "hello world" ;
     * var n:Number  = 4 ;
     * var b:Boolean = true ;
     * 
     * trace("# Serialize \r") ;
     * 
     * trace("- a : " + JSON.serialize( a ) )  ;
     * trace("- o : " + JSON.serialize( o ) )  ;
     * trace("- s : " + JSON.serialize( s ) )  ;
     * trace("- n : " + JSON.serialize( n ) )  ;
     * trace("- b : " + JSON.serialize( b ) )  ;
     * 
     * trace ("\r# Deserialize \r") ;
     * 
     * var source:String = '[ { "prop1" : 0xFF0000 , prop2:2, prop3:"hello", prop4:true} , 2, true, 3, [3, 2] ]' ;
     * 
     * o = JSON.deserialize(source) ;
     * 
     * var l:uint = o.length ;
     * for (var i:uint = 0 ; i &lt; l ; i++)
     * {
     *     trace("> " + i + " : " + o[i] + " -> typeof :: " + typeof(o[i])) ;
     *     if (typeof(o[i]) == "object")
     *     {
     *         for (var each:String in o[i])
     *         {
     *             trace("    > " + each + " : " + o[i][each] + " :: " + getClassName(o[i][each]) ) ;
     *         }
     *     }
     * }
     * 
     * trace ("\r# JSONError \r") ;
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
     * </code>
     * </listing>
     */
    public var JSON:JSONSerializer = new JSONSerializer() ;
}