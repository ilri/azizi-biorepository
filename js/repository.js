var Main = {
   ajaxParams: {successMssg: undefined, div2Update: undefined}, successMssg: undefined, title: 'Label Printing'
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
   }
};