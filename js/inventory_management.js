var InventoryManager = {
   submitNewIssueance: function() {
      if(this.validateInput()) {
         return true;
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

      $("#item").val($("#item").val().trim());
      $("#date").val($("#date").val().trim());
      $("#issued_to").val($("#issued_to").val().trim());
      $("#project").val($("#project").val().trim());
      $("#chargeCode").val($("#chargeCode").val().trim());
      $("#pp_unit").val($('#pp_unit').val().trim());

      if($("#item").val() === "") {
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the name of the issued item', error:true});
         $("#item").focus();
         return false;
      }
      else if($("#issued_to").val().split(" ").length<2) {
         Notification.show({create:true, hide:true, updateText:false, text:'You have to enter at least two names', error:true});
         $("#issued_to").focus();
         return false;
      }

      if($("#date").val() === "") {
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the date', error:true});
         $("#date").focus();
         return false;
      }

      /*if($("#project").val() === "") {
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the project', error:true});
         $("#project").focus();
         return false;
      }*/
      if($("#borrowed").val()== "no" && $('#pp_unit').val().match(/[A-Z]{3}[0-9]+/i) === null){
          Notification.show({create:true, hide:true, updateText:false, text:"Please enter the price per unit e.g KES200", error:true});
          $("#pp_unit").focus();
          return false;
      }
      if($("#borrowed").val()== "no" && $("#chargeCode").val() === "") {
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
                           apprv_comment:$("#apprv_comment").val()
                        }, function(){
                           console.log("response recieved, email should have been sent");
                           $("#past_requests").flexReload();
                           $("#newAmountApproved").val("");
                           $("#apprv_comment").val("");
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
   },
   
   toggleBorrowMode: function(){
       if($("#borrowed").val()=="yes"){
           $("#not_borrowed_sec").hide();
           $("#borrowed_sec").show();
       }
       else{
           $("#not_borrowed_sec").show();
           $("#borrowed_sec").hide();
       }
   },
   
   setReturned: function() {
       
   }
};
