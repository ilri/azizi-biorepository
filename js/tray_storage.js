/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
var TrayStorage = {
   
   /**
    * 
    * @returns {Boolean}
    */
   submitNewRequest : function(){
      if(this.validateInput() === true){
         return true;
      }
      return false;
   },
   
   validateInput: function(){
      if (typeof(String.prototype.trim) === "undefined") {
         String.prototype.trim = function()
         {
            return String(this).replace(/^\s+|\s+$/g, '');
         };
      }
      $("#tray_label").val($("#tray_label").val().trim());
      
      if($("#tray_label").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the label no the tray', error:true});
         $("#tray_label").focus();
         return false;
      }
      if($("#tank").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select a tank', error:true});
         $("#tank").focus();
         return false;
      }
      if($("#sector").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select a sector', error:true});
         $("#sector").focus();
         return false;
      }
      if($("#tower").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select a tower', error:true});
         $("#tower").focus();
         return false;
      }
      if($("#position").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select a tower position', error:true});
         $("#tank").focus();
         return false;
      }
      if($("#status").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select the tray\'s status', error:true});
         $("#tank").focus();
         return false;
      }
      
      return true;
   }
};