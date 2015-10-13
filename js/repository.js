var Main = {
   ajaxParams: {successMssg: undefined, div2Update: undefined}, successMssg: undefined, title: 'Label Printing',
   reEscape: new RegExp('(\\' + ['/', '.', '*', '+', '?', '|', '(', ')', '[', ']', '{', '}', '\\'].join('|\\') + ')', 'g')
};

var Repository = {
   /**
    * Checks the entered user credentials and submits the data to the server
    */
   submitLogin: function() {
      var userName = $('[name=username]').val(), password = $('[name=password]').val();
      if (userName == '') {
         alert('Please enter your username!');
         return false;
      }
      if (password == '') {
         alert('Please enter your password!');
         return false;
      }

      $('[name=md5_pass]').val($.md5(password));
      //$('[name=password]').val('');


     var encrypt = new JSEncrypt();
     //encrypt.setPublicKey($('#public_key').val());
     encrypt.setPublicKey(Repository.publicKey);
     var cipherText = encrypt.encrypt($('[name=password]').val());
     $('[name=password]').val(cipherText);

      return true;
   },

   /**
    * Formats the autocomplete suggestions
    *
    * @param {type} value
    * @param {type} data
    * @param {type} currentValue
    * @returns {unresolved}
    */
    fnFormatResult: function (value, searchString) {
//      if(currentValue.context !== undefined){ return; }
      var pattern = '(' + searchString.replace(Main.reEscape, '\\$1') + ')';
      return value.data.replace(new RegExp(pattern, 'gi'), '<strong>$1<\/strong>');
   },

   /**
   * Show a notification on the page
   *
   * @param   message     The message to be shown
   * @param   type        The type of message
   */
   showNotification: function(message, type){
   if(type === undefined) { type = 'error'; }

   $('#messageNotification div').html(message);
   if($('#messageNotification').jqxNotification('width') === undefined){
      $('#messageNotification').jqxNotification({
         width: 350, position: 'top-right', opacity: 0.9,
         autoOpen: false, animationOpenDelay: 800, autoClose: true, autoCloseDelay: 3000, template: type
       });
   }
   else{ $('#messageNotification').jqxNotification({template: type}); }

   $('#messageNotification').jqxNotification('open');
   }
};