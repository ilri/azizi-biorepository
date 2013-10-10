var Main = {
   ajaxParams: {successMssg: undefined, div2Update: undefined}, successMssg: undefined, title: 'Label Printing'
};

var LN2Transferer = {
   submitNewTransfer: function() {
      if(this.validateInput()) {
         return true;
      }
      else {
         return false;
      }
      return false;
   },
   validateInput: function() {
      if (typeof(String.prototype.trim) === "undefined") {
         String.prototype.trim = function()
         {
            return String(this).replace(/^\s+|\s+$/g, '');
         };
      }
      
      $("#technician").val($("#technician").val().trim());
      
      if($("#technician").val() === "") {
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the name of the technician', error:true});
         $("#technician").focus();
         return false;
      }
      else if($("#technician").val().split(" ").length<2) {
         Notification.show({create:true, hide:true, updateText:false, text:'You have to enter at least two names', error:true});
         $("#technician").focus();
         return false;
      }
      
      if($("#date").val() === "") {
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the tranfer date', error:true});
         $("#date").focus();
         return false;
      }
      
      /*if($("#litres").val() === "") {
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the amout of litres transfered', error:true});
         $("#litres").focus();
         return false;
      }
      else if(isNaN($("#litres").val())) {
         Notification.show({create:true, hide:true, updateText:false, text:'Amount in litres you entered is not a number', error:true});
         $("#litres").focus();
         return false;
      }*/
      
      if($("#pBeforeTransfer").val() === "") {
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the production level before the transfer', error:true});
         $("#pBeforeTransfer").focus();
         return false;
      }
      else if(isNaN($("#pBeforeTransfer").val())) {
         Notification.show({create:true, hide:true, updateText:false, text:'Production before transfer entered is not a percentage', error:true});
         $("#pBeforeTransfer").focus();
         return false;
      }
      else if(($("#pBeforeTransfer").val()-100) < -80 || ($("#pBeforeTransfer").val()-100) > 0){
         Notification.show({create:true, hide:true, updateText:false, text:'Production before transfer should be between 20 and 100 percent', error:true});
         $("#pBeforeTransfer").focus();
         return false;
      }
      
      if($("#pAfterTransfer").val() === "") {
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the production level after the transfer', error:true});
         $("#pAfterTransfer").focus();
         return false;
      }
      else if(isNaN($("#pAfterTransfer").val())) {
         Notification.show({create:true, hide:true, updateText:false, text:'Production after transfer entered is not a percentage', error:true});
         $("#pAfterTransfer").focus();
         return false;
      }
      else if(($("#pAfterTransfer").val()-100) < -100 || ($("#pAfterTransfer").val()-100) > 0){
         Notification.show({create:true, hide:true, updateText:false, text:'Production after transfer should be between 0 and 100 percent', error:true});
         $("#pAfterTransfer").focus();
         return false;
      }
      else if(($("#pBeforeTransfer").val() - $("#pAfterTransfer").val()) < 20 || ($("#pBeforeTransfer").val() - $("#pAfterTransfer").val()) > 100){
         Notification.show({create:true, hide:true, updateText:false, text:'Difference in production levels should be between 20 and 100 percent', error:true});
         $("#pAfterTransfer").focus();
         return false;
      }
      
      if($("#pressureLoss").val() === "") {
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the pressure loss', error:true});
         $("#pressureLoss").focus();
         return false;
      }
      else if(isNaN($("#pressureLoss").val())) {
         Notification.show({create:true, hide:true, updateText:false, text:'Pressure loss entered is not a number', error:true});
         $("#pressureLoss").focus();
         return false;
      }
      return true;
   }
};
