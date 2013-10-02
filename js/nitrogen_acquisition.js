var Main = {
   ajaxParams: {successMssg: undefined, div2Update: undefined}, successMssg: undefined, title: 'Label Printing'
};

var NAcquisition = {
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
      
      var publicKey;
      $.ajax({
         url: "./modules/mod_get_rsa_pub_key.php",
         async: false,
         success: function (data, textStatus, jqXHR) {
            publicKey = data;
         }
      });
     
     var encrypt = new JSEncrypt();
     //encrypt.setPublicKey($('#public_key').val());
     encrypt.setPublicKey(publicKey);
     var cipherText = encrypt.encrypt($('[name=password]').val());
     console.log(cipherText);
     $('[name=password]').val(cipherText);
     
      return true;
   },
   submitNewRequest: function() {
      if(this.validateInput()) {
         return true;
      }
      else {
         return false;
      }
      return false;
   },
   validateInput: function() {
      if($("#user").val() === "") {
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the name of the person making the request', error:true});
         $("#user").focus();
         return false;
      }
      if($("#date").val() === "") {
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the date', error:true});
         $("#date").focus();
         return false;
      }
      if($("#amount").val() === "") {
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the amount requested in KGs', error:true});
         $("#amount").focus();
         return false;
      }
      else if(isNaN($("#amount").val())) {
         Notification.show({create:true, hide:true, updateText:false, text:'Amount entered is not a number', error:true});
         $("#amount").focus();
         return false;
      }
      if($("#project").val() === "") {
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the project', error:true});
         $("#project").focus();
         return false;
      }
      if($("#chargeCode").val() === "") {
         Notification.show({create:true, hide:true, updateText:false, text:"Please enter the project's charge code", error:true});
         $("#chargeCode").focus();
         return false;
      }
      return true;
   },
   
   changeAmountApproved: function(com, grid) {
      if(com === 'Change Amount Approved'){
         $(".trSelected", grid).each(function () {
            var id = $(this).attr('id');
            id = id.substring(id.lastIndexOf('row')+3);
            $("#dialog-modal").dialog({
               modal: true,
               draggable: true,
               resizable: false,
               position: ['center','center'],
               show: 'blind',
               hide: 'blind',
               width: 400,
               dialogClass: 'ui-dialog-osx',
               buttons: {
                  "Change": function() {
                     if($("#newAmountApproved").val()!==""){
                        //TODO: add logic for changing column value from server
                        $.post("index.php?page=acquisition&do=setAmountApproved", {
                           rowID:id,
                           amountApproved:$("#newAmountApproved").val()
                        }, function(){
                           $("#past_requests").flexReload();
                           $("#newAmountApproved").val("");
                        });
                        $(this).dialog("close");
                     }
                  }
               }
            });
         });
      }
   }
};