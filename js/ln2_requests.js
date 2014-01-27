var Ln2Requests = {
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
      if (typeof(String.prototype.trim) === "undefined") {
         String.prototype.trim = function()
         {
            return String(this).replace(/^\s+|\s+$/g, '');
         };
      }

      $("#user").val($("#user").val().trim());
      $("#amount").val($("#amount").val().trim());
      $("#project").val($("#project").val().trim());
      $("#chargeCode").val($("#chargeCode").val().trim());

      if($("#user").val() === "") {
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the name of the person making the request', error:true});
         $("#user").focus();
         return false;
      }
      else if($("#user").val().split(" ").length<2) {
         Notification.show({create:true, hide:true, updateText:false, text:'You have to enter at least two names', error:true});
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

      /*if($("#project").val() === "") {
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the project', error:true});
         $("#project").focus();
         return false;
      }*/

      if($("#chargeCode").val() === "") {
         Notification.show({create:true, hide:true, updateText:false, text:"Please enter the project's charge code", error:true});
         $("#chargeCode").focus();
         return false;
      }
      return true;
   },

   changeAmountApproved: function(com, grid) {
      if(com === 'Set Amount Approved'){
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
               width: 500,
               dialogClass: 'ui-dialog-osx',
               buttons: {
                  "Set": function() {
                     if($("#newAmountApproved").val()!==""){
                        //TODO: add logic for changing column value from server
                        $.post("index.php?page=ln2_requests&do=setAmountApproved", {
                           rowID:id,
                           amountApproved:$("#newAmountApproved").val(),
                           comment:$("#comment").val()
                        }, function(){
                           console.log("response recieved, email should have been sent");
                           $("#past_requests").flexReload();
                           $("#newAmountApproved").val("");
                           $("#comment").val("");
                        });
                        $(this).dialog("close");
                     }
                  }
               }
            });
         });
      }
   },

   fetchProjects: function() {
      var json;
      $.ajax({
         url: "index.php?page=ln2_requests&do=getProjects",
         async: false,
         success: function (data, textStatus, jqXHR) {
            json = eval(data);
         }
      });
      return json;
   }
};
