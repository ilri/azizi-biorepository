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
      $("#quantity").val($("#quantity").val().trim());
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
      if($("#quantity").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the quantity of the item', error:true});
         $("#quantity").focus();
         return false;
      }
      else if(isNaN($("#quantity").val()) || !isFinite($("#quantity").val())){//if quantity is not a number or is not a finite number
         Notification.show({create:true, hide:true, updateText:false, text:'Quantity should be a number', error:true});
         $("#quantity").focus();
         return false;
      }
      if($("#issued_to").val().split(" ").length<2) {
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
      if($("#borrowed").val()== "no" && $('#pp_unit').val().match(/[0-9]+/i) === null){
          Notification.show({create:true, hide:true, updateText:false, text:"Please enter a valid price", error:true});
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
   
   setReturned: function(com, grid) {
      if($(".trSelected", grid).length > 0){
         var left = window.innerWidth/2 - $("#return_comment_div").width()/2;
         var top = window.innerHeight/2 - $("#return_comment_div").height()/2;

         $("#return_comment_div").css("left", left);
         $("#return_comment_div").css("top", top);

         $("#return_comment_div").show();

         $("#return_comment_btn").click(function(){
            $(".trSelected", grid).each(function(){
               var id = $(this).attr("id");
               id = id.replace("row", "");
               var comment = $("#return_comment").val();
               $.post("index.php?page=inventory&do=return", {
                  id:id,
                  comment:comment
               }, function(){
                  $("#issued_items").flexReload();
                  $("#return_comment").val("");
                  $("#return_comment_div").hide();
               });
            });
         });
      }
   },
   
   downloadRechargeFile: function() {
      var url = "mod_ajax.php?page=inventory&do=ajax&action=download_recharge_file";
      
      $("#hiddenDownloader").remove();
      $('#repository').append("<iframe id='hiddenDownloader' style='display:none;' />");
      $("#hiddenDownloader").attr("src", url);
   }
};
