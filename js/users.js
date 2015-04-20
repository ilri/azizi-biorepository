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

   window.users.createUserBtn = jQuery("#create_user_btn");
   window.users.createUserBtn.bind("click", window.users.validateInput);

   window.users.groupList = jQuery("#group_list");

   window.users.groupIDs = new Array();
   window.users.addToGroupBtn = jQuery("#add_group_btn");
   window.users.addToGroupBtn.click(function(){
      window.users.addGroup();
   });

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
   if(window.users.context == "edit_user"){
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
         console.log(userData);
         if(userData.length == 1){
            window.users.userData = userData[0];
            $("#sname").val(userData[0].sname);
            $("#onames").val(userData[0].onames);
            $("#username").val(userData[0].login);
            $("#project option[value="+userData[0].project+"]").attr('selected', 'selected');
            $("#ldap").val(userData[0].ldap);
            $("#allowed").val(userData[0].allowed);

            window.users.togglePasswords();

            //clear out group list
            window.users.groupList.empty();
            window.users.groupIDs = new Array();

            for(var gIndex = 0; gIndex < userData[0].groups.length; gIndex++){
               window.users.addGroup(userData[0].groups[gIndex]);
            }
         }
      }
   });
};

/**
 * This function adds a group to the list of groups a user is in
 */
Users.prototype.addGroup = function(data) {
   if(typeof data === 'undefined') var selectedGroup = $("#group").find(":selected")[0];
   else var selectedGroup = data;

   if(selectedGroup.text.length > 0){
      if(jQuery.inArray(selectedGroup.id, window.users.groupIDs) == -1){//if group not already in list
         var html = "<div id='group_"+selectedGroup.id+"' style='cursor:pointer;float:left;width:100%;height:10px;text-align:center;line-height:10px;'>"+selectedGroup.text+"</div>";
         window.users.groupList.append(html);
         window.users.groupIDs.push(selectedGroup.id);

         $("#group_"+selectedGroup.id).click({"id":selectedGroup.id}, function(e){
            var groupID = e.data.id;
            $(this).remove();

            window.users.groupIDs.splice(jQuery.inArray(groupID, window.users.groupIDs), 1);
         });
      }
   }
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