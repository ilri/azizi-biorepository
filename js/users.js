/**
 * Javascript class corresponding to the users module
 * @returns {undefined}
 */
function Users(context, pwSettingsS, publicKey){
   window.users = this;

   //reset action post variable
   jQuery("#action").val("");

   //init variables
   window.users.context = context;

   window.users.groupList = jQuery("#group_list");
   window.users.groupIDs = new Array();

   window.users.pass1 = jQuery("#pass_1");
   window.users.pass2 = jQuery("#pass_2");
   window.users.email = jQuery("#email");
   window.users.ldap = jQuery("#ldap");
   window.users.ldap.change(function(){
      console.log("value of ldap = "+window.users.ldap.val());
      window.users.togglePasswords();
   });

   window.users.userData = null;

   window.users.eUsers = new Array();//array of existing users
   window.users.getUsers();

   window.users.pwSettings = jQuery.parseJSON(pwSettingsS);
   window.users.publicKey = publicKey;

   //init things specific to edit_user context
   if(window.users.context === "edit_user"){
      window.users.exisitingUsers  = jQuery("#existing_users");
      window.users.exisitingUsers.change({}, function(){
         var selectedUser = window.users.exisitingUsers.find(":selected");
         if(selectedUser[0].id.length > 0){
            console.log("getting data for", selectedUser[0].id);
            window.users.getUserData(selectedUser[0].id);
         }
      });
   }

   //check if user using ldap or not and disable password fields accordingly
   window.users.togglePasswords();
}

/**
 * This method validates user input
 * Things that are checked include:
 *    - uniqueness of the username
 *    - strength of the password
 *    -
 * @returns {boolean} True if everything is fine
 */
Users.prototype.validateInput = function() {

   if($("#sname").val().length === 0){
      Notification.show({create:true, hide:true, updateText:false, text:'Please enter a surname', error:true});
      $("#sname").focus();
      return false;
   }

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

   if($("#project").find(":selected").text().length === 0){
      Notification.show({create:true, hide:true, updateText:false, text:'Please select a project', error:true});
      $("#project").focus();
      return false;
   }

   if($("#username").val().length === 0){
      Notification.show({create:true, hide:true, updateText:false, text:'Please enter a username', error:true});
      $("#username").focus();
      return false;
   }
   else{//check if the specified username already exists
      if(window.users.userData == null || window.users.userData.login != $("#username").val()){//if adding new user or updating username for an existing user
         for(var uIndex = 0; uIndex < window.users.eUsers.length; uIndex++){
            if(window.users.eUsers[uIndex].login === $("#username").val()){
               console.log(window.users.eUsers[uIndex]);
               Notification.show({create:true, hide:true, updateText:false, text:'Username provided already in use', error:true});
               $("#username").focus();
               return false;
            }
         }
      }
   }
   var emailRegex = /.+@.+\.[a-z0-9]+/i;

   if(window.sub_module === 'create_account'){
      // since we are creating an account we must have an email specified
      if($("#email").val().length === 0){
         Notification.show({create:true, hide:true, updateText:false, text:'Please specify an email address', error:true});
         $("#email").focus();
         return false;
      }
      else if($("#email").val().match(emailRegex) == null){
         Notification.show({create:true, hide:true, updateText:false, text:'Incorrect email address', error:true});
         $("#email").focus();
         return false;
      }
   }

   //if you have reached here, then everything is fine
   //set the action post variable
   jQuery("#action").val(window.users.context);

   if(window.users.userData != null){
      $("#user_id").val(window.users.userData.id);
   }

   //add user's groups to the hidden input
   jQuery("#user_groups").val(window.users.groupIDs.join(","));

   //encrypt the password
   if($('#pass_1').val().length > 0){//only encrypt if password is set. Password might not be set if we are updating
      var encrypt = new JSEncrypt();

      encrypt.setPublicKey(window.users.publicKey);
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

/**
 * Save the changes defined so far by the user
 * @returns {undefined}
 */
Users.prototype.saveChanges = function(){
   // first validate the data entered
   if(users.validateInput() === false){ return; }

   // format all the data as a nice json object and send it to the server for saving
   var formData = $('.user_form').serializeArray();
   var selectedGroups = [];
   $.each($("#to_groups").jqxListBox('getItems'), function(i, that){
      selectedGroups[selectedGroups.length] = that.value;
   });
   var jsonData = new FormData();
   $.each(formData, function(i, that){
      jsonData.append(that.name, that.value);
   });
   jsonData.append('user_groups', selectedGroups);
   jsonData.append('user_id',  users.userData.id);

    $.ajax({
      type:"POST", url: 'mod_ajax.php?page=users&do=manage_account&action=edit_user', dataType:'json', cache: false, contentType: false, processData: false, data: jsonData,
      success: function (data) {
         if(data.error === true){
            Repository.showNotification(data.mssg, 'error');
            return false;
         }
         else{
            // all seems good, so refresh the page
            Repository.showNotification(data.mssg, 'success');
            $('.user_form').clearForm();
            $("#to_groups").jqxListBox('clear');
            $("#from_groups").jqxListBox({ source: Main.allGroups });
         }
     }
   });
};

/**
 * This function gets the list of users from the database
 */
Users.prototype.getUsers = function() {
   jQuery.ajax({
      url: "mod_ajax.php?page=users&do=ajax&action=get_users",
      async: true,
      success: function (data) {
         window.users.eUsers = jQuery.parseJSON(data);
      }
   });
};

/**
 * This function gets data corresponding to the provided user id
 * @param {int} id The database id for the user
 */
Users.prototype.getUserData = function(id){
   console.log(id);
   jQuery.ajax({
      url: "mod_ajax.php?page=users&do=ajax&action=get_user_data",
      async: true,
      data: {"id":id},
      success: function (data) {
         var userData = jQuery.parseJSON(data);
         if(userData.length === 1){
            window.users.userData = userData[0];
            $("#sname").val(userData[0].sname);
            $("#onames").val(userData[0].onames);
            $("#username").val(userData[0].login);
            $("#email").val(userData[0].email);
            $("#project option[value="+userData[0].project+"]").attr('selected', 'selected');
            $("#ldap").val(userData[0].ldap);
            $("#allowed").val(userData[0].allowed);

            window.users.togglePasswords();

            //clear out group list
            window.users.groups = userData[0].groups;
            window.users.groupIDs = new Array();

            $("#to_groups").jqxListBox('clear');
            $.each(userData[0].groups, function(i, that){
               $("#from_groups").jqxListBox('disableItem', that.id);
               $("#to_groups").jqxListBox('addItem', that);
            });
         }
      }
   });
};

/**
 * This function adds a group to the list of groups a user is in
 */
Users.prototype.addToGroup = function() {
   // get the checked groups and add them to the list of user groups
   var checkedGroups = $("#from_groups").jqxListBox('getCheckedItems');
   $.each(checkedGroups, function(i, that){
      $("#from_groups").jqxListBox('uncheckItem', that.value);
      $("#to_groups").jqxListBox('addItem', that);
      $("#from_groups").jqxListBox('disableItem', that.value);
   });
};

/**
 * Remove a user from a group
 * @returns {undefined}
 */
Users.prototype.removeFromGroup = function(){
   var checkedGroups = $("#to_groups").jqxListBox('getCheckedItems');
   $.each(checkedGroups, function(i, that){
      $("#from_groups").jqxListBox('enableItem', that.value);
      $("#to_groups").jqxListBox('removeItem', that);
   });
};

/**
 * This function disables/enables the password fields based on ldap auth
 *
 * @returns {undefined}
 */
Users.prototype.togglePasswords = function(){
   if($("#ldap").val() == 0){//user using local auth
      $("#pass_1").prop("disabled", false);
      $("#pass_2").prop("disabled", false);
   }
   else {
      $("#pass_1").prop("disabled", true);
      $("#pass_2").prop("disabled", true);
   }
};