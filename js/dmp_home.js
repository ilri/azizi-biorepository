function DMPHome(server, user, session) {
   //hide all the repository elements we dont need
   window.dhome = this;
   window.dhome.server = server;
   window.dhome.user = user;
   window.dhome.session = session;
   //initialize source for project_list_box
   $(document).ready(function(){
      console.log("DOM ready");
      window.dhome.documentReady();
   });
}

/**
 * This function initialized all resources that need to be initialized after the
 * DOM is ready
 * @returns {undefined}
 */
DMPHome.prototype.documentReady = function () {
   $("#new_project_btn").jqxButton({width: '500px'});
   window.dhome.initProjectList();
   
   $("#new_project_btn").click(function () {
      window.dhome.openProject();
   });
   $("#inotification_pp").jqxNotification({position: "top-right", opacity: 0.9, autoOpen: false, autoClose: true, template:"info"});
   $("#enotification_pp").jqxNotification({position: "top-right", opacity: 0.9, autoOpen: false, autoClose: false, template:"error"});
   if(window.dhome.session == null || window.dhome.session.length == 0) {
      $("#enotification_pp").html("User not registered");
      $("#enotification_pp").jqxNotification("open");
   }
};

/**
 * This function initializes the source for the project_list_box jqxList
 * @returns {undefined}
 */
DMPHome.prototype.initProjectList = function() {
   console.log("Initializing  project list");
   var source = {
      datatype: "json",
      datafields: [
         {name: 'workflow_name'},
         {name: 'time_created'},
         {name: 'workflow_id'},
         {name: 'created_by'},
         {name: 'label'}
      ],
      root: 'workflows',
      async: true,
      url: "mod_ajax.php?page=odk_workflow&do=get_workflows",
      data:{
         token: {server: window.dhome.server, user: window.dhome.user, session: window.dhome.session}
      },
      type: "POST",
      id: "workflow_id",
      beforeprocessing: function(data) {
         //console.log("project data = ", data);
         
         //TODO: alert user if data is null
         if(data != null) {
            console.log(data);
            Number.prototype.toOrdinal = function() {
               var n = this.valueOf(),
               s = [ 'th', 'st', 'nd', 'rd' ],
               v = n % 100;
               return n + (s[(v-20)%10] || s[v] || s[0]);
            };
            Number.prototype.timePad = function() {
               var n = this.valueOf()+"";//convert to string
               if(n.length == 1) n = "0"+n;
               return n;
            };
            var months = ["Jan", "Feb", "March", "April", "May", "June", "July", "Aug", "Sept", "Oct", "Nov", "Dec"];

            for(var index = 0; index < data.workflows.length; index++) {
               
               var today = new Date();
               var workflowDate = new Date(data.workflows[index].time_created);
               var year = -1;
               if(today.getFullYear() != workflowDate.getFullYear()) year = workflowDate.getFullYear();
               var month = -1;
               if(today.getMonth() != workflowDate.getMonth() || year != -1) month = workflowDate.getMonth();
               var day = -1;
               if(today.getDate() != workflowDate.getDate() || month != -1) day = workflowDate.getDate();
               if(day != -1) day = " " + day.toOrdinal();
               else day = "";
               if(month == -1) month = "";
               else month = " " + months[month];
               if(year == -1) year = "";
               else year = " " + year;
               var timeString = day + month + year;
               if(timeString.length > 0)timeString = "on the "+timeString+" at ";
               else timeString = "at ";
               timeString = timeString + (workflowDate.getHours()).timePad() + ":" + workflowDate.getMinutes().timePad() + ":" + workflowDate.getSeconds().timePad();
               console.log(data.workflows[index]);
               //var creationDate = new Date(data.workflows[index].time_created);
               data.workflows[index].workflow_name = data.workflows[index].workflow_name + " created " + timeString;
            }
         }
      }
   };
   
   window.dhome.projectListAdapter = new $.jqx.dataAdapter(source);
   $("#projects_list_box").jqxListBox({width: '500px', height:'150px', source: window.dhome.projectListAdapter, theme: '', displayMember: 'workflow_name', valueMember: 'workflow_id'});
   $("#projects_list_box").bind("select", function(event){
      var project = event.args.item.value;
      console.log(project);
      
      window.dhome.openProject(project);
   });
};

/**
 * This function connects to a project
 * 
 * @param {String}   (Optional)The project to connect to
 * @returns {undefined}
 */
DMPHome.prototype.openProject = function(project) {
   if(typeof project === "undefined") project = "";
   window.location.href = window.location.origin + window.location.pathname + "?page=dmp&do=view_schema&project="+project+"&session="+window.dhome.session;
};