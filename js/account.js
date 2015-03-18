/**
 * Javascript class corresponding to the users module
 * @returns {undefined}
 */
function Account(pwSettingsS, publicKey, ldap){
   console.log("account object initialized");
   window.account = this;
   
   window.account.pwSettings = jQuery.parseJSON(pwSettingsS);
   window.account.publicKey = publicKey;
   window.account.ldap = ldap;
   
   //$("#update_user_btn").bind('click', window.account.validateInput);
}

/**
 * This method validates user input
 * Things that are checked include:
 *    - uniqueness of the username
 *    - strength of the password
 *    - 
 * @returns {boolean} True if everything is fine
 */
Account.prototype.validateInput = function() {
   if($("#onames").val().length === 0){
      Notification.show({create:true, hide:true, updateText:false, text:'Please provide a name for the user', error:true});
      $("#onames").focus();
      return false;
   }
   
   if($("#sname").val().length === 0){
      Notification.show({create:true, hide:true, updateText:false, text:'Please enter a surname', error:true});
      $("#sname").focus();
      return false;
   }
   
   var emailRegex = /.+@.+\.[a-z0-9]+/i;
   
   if($("#email").val().length == 0){
      Notification.show({create:true, hide:true, updateText:false, text:'Please specify an email address', error:true});
      $("#email").focus();
      return false;
   }
   else if($("#email").val().match(emailRegex) == null){
      Notification.show({create:true, hide:true, updateText:false, text:'Incorrect email address', error:true});
      $("#email").focus();
      return false;
   }
   if($("#ldap").val() == 0){//only check the correctness of the passowrd if user not going to use ldap auth
      if($("#old_pass").val().length === 0){
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter your current password', error:true});
         $("#old_pass").focus();
         return false;
      }
      
      if($("#pass_1").val().length > 0 || $("#pass_2").val().length > 0){//user wants to modify the password
         if($("#pass_1").val().length === 0){
            Notification.show({create:true, hide:true, updateText:false, text:'Please enter a password', error:true});
            $("#pass_1").focus();
            return false;
         }
         else if($("#pass_1").val() !== $("#pass_2").val()){
            Notification.show({create:true, hide:true, updateText:false, text:'Passwords do not match', error:true});
            $("#pass_1").focus();
            return false;
         }
         else{//check the strength of the password
            if($("#pass_1").val().length < window.account.pwSettings.minLength){
               Notification.show({create:true, hide:true, updateText:false, text:'The password you entered is too short', error:true});
               $("#pass_1").focus();
               return false;
            }

            if(window.account.pwSettings.alphaChars == true){
               if($("#pass_1").val().match(/[a-zA-Z]/g) == null){
                  Notification.show({create:true, hide:true, updateText:false, text:'The password you entered does not meet the minimum requirments', error:true});
                  $("#pass_1").focus();
                  return false;
               }
            }

            if(window.account.pwSettings.numericChars == true){
               if($("#pass_1").val().match(/[0-9]/g) == null){
                  Notification.show({create:true, hide:true, updateText:false, text:'The password you entered does not meet the minimum requirments', error:true});
                  $("#pass_1").focus();
                  return false;
               }
            }

            if(window.account.pwSettings.specialChars == true){
               if($("#pass_1").val().match(/[^a-z0-9]/ig) == null){//regex for non alphanumeric characters
                  Notification.show({create:true, hide:true, updateText:false, text:'The password you entered does not meet the minimum requirments', error:true});
                  $("#pass_1").focus();
                  return false;
               }
            }
         }
      }
      
   }
   else {//user going to use ldap auth, reset passwords to blank
      $("#pass_1").val("");
      $("#pass_2").val("");
   }
   
   //encrypt the password
   if($('#old_pass').val().length > 0){//only encrypt if password is set. Password might not be set if we are updating
      var encrypt = new JSEncrypt();
   
      encrypt.setPublicKey(window.account.publicKey);
      var cipherText = encrypt.encrypt($('#old_pass').val());
      $('#old_pass').val(cipherText);
   }
   
   if($('#pass_1').val().length > 0){//only encrypt if password is set. Password might not be set if we are updating
      var encrypt = new JSEncrypt();
   
      encrypt.setPublicKey(window.account.publicKey);
      var cipherText = encrypt.encrypt($('#pass_1').val());
      $('#pass_1').val(cipherText);
      $('#pass_2').val(cipherText);
   }
   else {
      $('#pass_1').val("");
      $('#pass_2').val("");
   }
   
   return true;
};