/**
 * This is the constructor
 * @returns {undefined}
 */
function Uploader() {
   window.uploader = this;
}

Uploader.prototype.validateInput = function () {
   var emailRegex = /\S+@\S+\.\S+/;
   
   if($("#excel_file").val() === ""){
      Notification.show({create:true, hide:true, updateText:false, text:'Please provide the Excel file', error:true});
      $("#excel_file").focus();
      return false;
   }
   if($("#email").val() === ""){
      Notification.show({create:true, hide:true, updateText:false, text:'Please enter your email address', error:true});
      $("#email").focus();
      return false;
   }
   else if(emailRegex.test($("#email").val()) == false){
      Notification.show({create:true, hide:true, updateText:false, text:'Please enter a valid email address', error:true});
      $("#email").focus();
      return false;
   }
   if($("#collaborators").val().length > 0){//user has specified collaborators
      var collaborators = $("#collaborators").val().split(";");
      for(var index = 0; index < collaborators.length; index++){
         var currCollaborator = jQuery.trim(collaborators[index]);
         var cRegex = /\S+/;
         if(cRegex.test(currCollaborator) == false){
            Notification.show({create:true, hide:true, updateText:false, text:'Please enter valid usernames', error:true});
            $("#collaborators").focus();
            return false;
         }
      }
   }
   if($("#upload_type").val() === ""){
      Notification.show({create:true, hide:true, updateText:false, text:'Please select the type of upload', error:true});
      $("#upload_type").focus();
      return false;
   }
   return true;
};
