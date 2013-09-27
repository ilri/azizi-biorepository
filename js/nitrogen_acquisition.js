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

      //we have all that we need, lets submit this data to the server
      $('[name=md5_pass]').val($.md5(password));
      //$('[name=password]').val('');
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
   }
};
