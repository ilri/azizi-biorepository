/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
function Groups(context, existingGroups) {
   window.groups = this;
   
   window.groups.context = context;
   window.groups.allActions = jQuery("#all_actions");
   window.groups.addActionBtn = jQuery("#add_action_btn");
   window.groups.actionList = jQuery("#action_list");
   window.groups.createGroupBtn = jQuery("#create_group_btn");
   window.groups.createGroupBtn.bind("click", window.groups.validateInput);
   window.groups.eGroups = jQuery.parseJSON(existingGroups);
   
   window.groups.actionIDs = new Array();
   
   window.groups.addActionBtn.click(function(){
      console.log("click");
      if(window.groups.allActions.find(":selected")[0].text.length > 0){
         console.log("click");
         window.groups.addAction(window.groups.allActions.find(":selected")[0]);
      }
   });
   
   window.groups.groupSelect = jQuery("#curr_group_name");
   window.groups.groupSelect.change({}, function(){
      var selectedGroup = $(this).find(":selected")[0];
      if(selectedGroup.text.length > 0){
         $("#group_id").val(selectedGroup.id);
         window.groups.getGroupData(selectedGroup.id, selectedGroup.text);
      }
   });
}

/**
 * This function adds an sub module action to a group
 * 
 * @returns {undefined}
 */
Groups.prototype.addAction = function(action){
   for(var aIndex = 0; aIndex < window.groups.actionIDs.length; aIndex++){//check if action already added
      if(window.groups.actionIDs[aIndex] == action.id){//already in the list
         return;
      }
   }
   console.log(action);
   if(action.text.substr(action.text.length - 1) == "-"){
      action.text = action.text + "All Actions";
   }
   var html = "<div id='action_"+action.id+"' style='cursor:pointer;float:left;width:100%;height:10px;text-align:center;line-height:10px;'>"+action.text+"</div>";
   
   window.groups.actionIDs.push(action.id);
   
   window.groups.actionList.append(html);
   
   $("#action_"+action.id).click({id:action.id},function(e){
      var actionID = e.data.id;
      jQuery(this).remove();
      
      window.groups.actionIDs.splice(jQuery.inArray(actionID, window.groups.actionIDs), 1);
   });
};

/**
 * This function checks if everything is fine with the add group form
 * 
 * @returns {Boolean}
 */
Groups.prototype.validateInput = function(){
   
   if($("#group_name").val().length == 0){
      Notification.show({create:true, hide:true, updateText:false, text:'Please give the group a name', error:true});
      $("#group_name").focus();
      return false;
   }
   else{//name set, check if there exists a group with the same name
      if(window.groups.context == 'add_group' || window.groups.groupSelect.find(":selected")[0].text != $("#group_name").val()){
         for(var gIndex = 0; gIndex < window.groups.eGroups.length; gIndex++){
            if(window.groups.eGroups[gIndex].name == $("#group_name").val()){
               Notification.show({create:true, hide:true, updateText:false, text:'A group with the same name already exists', error:true});
               $("#group_name").focus();
               return false;
            }
         }
      }
   }
   
   $("#group_actions").val(window.groups.actionIDs.join(","));
   
   $("#action").val(window.groups.context);
   
   return true;
};

Groups.prototype.getGroupData = function(groupID, groupName){
   console.log("group id = ",groupID);
   jQuery.ajax({
      url: "mod_ajax.php?page=users&do=ajax&action=get_group_data",
      async:true,
      data:{id:groupID},
      success:function(data){
         var jsonData = jQuery.parseJSON(data);
         $("#group_name").val(groupName);
         window.groups.actionList.empty();
         window.groups.actionIDs = new Array();
         for(var aIndex = 0; aIndex < jsonData.length; aIndex++){
            window.groups.addAction(jsonData[aIndex]);
         }
      }
   });
};