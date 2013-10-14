var Main = {
   ajaxParams: {successMssg: undefined, div2Update: undefined}, successMssg: undefined, title: 'Label Printing'
};

var LabelPrinter={

    /**
     * Checks the entered user credentials and submits the data to the server
        */
    submitLogin: function(){
       var userName = $('[name=username]').val(), password = $('[name=password]').val();
       if(userName == ''){
          alert('Please enter your username!');
          return false;
       }
       if(password == ''){
          alert('Please enter your password!');
          return false;
       }

       //we have all that we need, lets submit this data to the server
       $('[name=md5_pass]').val($.md5(password));
       $('[name=password]').val('');
       return true;
    },

   labelSetup: function(){
   if($('#labelTypesid').val()==0){
      alert("Please select a label type to modify its settings");
      return;
   }
   window.open("index.php?page=setup&id="+$('#labelTypesid').val(), '_self');
},

   fetchPrintedLabels: function(){
      var params;
      if($('#pl_printed')[0].innerHTML!=="&nbsp;"){ //we have fetched the printed labels before display them
         showHide('pl_printed');
         return;
      }
      Main.ajaxParams.successMssg='Successfully updated.';
      Main.ajaxParams.div2Update='pl_printed';
      Main.initSorting = true;
      params='flag=printedLabels';
      notificationMessage({create: true, hide:false, updatetext: false, text: 'updating ...'});
      $.ajax({type:"POST", url:'seamless.php', data:params, dataType:'text', success:ajaxUpdateInterface});
   },

   uploadsFile: function(){
      //check if the user has selected a label type to print
		if($("#labelTypesid").val()==0){
         createMessageBox('Please select the type of labels that you want to print.', doNothing, false);
         $('labelTypesid').focus();return;
		}
		if($("[type=file]").val()==""){
         createMessageBox('Please select a tab delimited file with the labels to generate.', doNothing, false);
         return;
		}
		$('[name="flag"]').val('upload_file');
      $('#seamless_upload').bind('onLoad', Labels.resultOnFileUpload);
      Labels.saveSettingsInterface();
   },

   saveSettingsInterface: function(){
      if($('#pl_settings').length==0){
         //create the interface for adding the details of this labels generation
         var settings={
            name: 'project', id: 'projectId',
            dispValues:new Array('AVID', 'IDEAL'), hidValues: new Array('avid', 'ideal'),
            initValue:'Project',selected: 0, enabled: true, type: 'single', width: undefined, size: undefined
         }
         var combo=generateCombo(settings);
         var content='<div id="pl_settings">\n\<table>\n\
         <tr><td>Project</td><td>'+combo+'</td><td>Prefix</td><td><input type="text" name="prefix" id="prefixId" value="" /></td></tr>\n\
         <tr><td>First Label</td><td><input type="text" name="first" id="firstId" value="" /></td><td>Last Label</td>\n\
             <td><input type="text" name="last" id="lastId" value="" /></td></tr>\n\
         <tr><td>Copies</td><td><input type="text" name="copies" id="copiesId" value="" /></td><td>Total</td>\n\
             <td><input type="text" name="total" id="totalId" value="" /></td></tr>\n\
         <tr><td>Remarks</td><td colspan="3"><textarea rows="2" cols="40" name="remarks" id="remarksId"></textarea></td></tr>\n\
         <tr><td colspan=4 style="text-align: center;"><input type="button" value="Save" onClick="Labels.saveSettings();" />\n\
             <input type="Reset" value="Cancel" onClick="Labels.closeSettings();" /></td></tr>';
         content+='</table></div>';
         $('#pl_header').before(content);
      }
      else showHide('pl_settings', 'show');
   },

   /*
    * Called when the file has been generated and is being downloaded
    */
   resultOnFileUpload: function(){
      notificationMessage({create: true, hide:true, updatetext: false, text: 'saved...', error: false});
   },

   /*
    * Start the saving process for the entered data
    */
   saveSettings: function(){
      //do the sanity checks on the data b4 accepting em
      var temp = $('#projectId').val();
      if(!isNaN(temp)){
         createMessageBox('Please select the project the labels will be used for.', doNothing, false);
         $('#projectId').val(0);$('#projectId').focus();return;
      }
      temp = $('#prefixId').val();
      if(!validate(temp, /[a-z]{3,5}/i) || temp===undefined || temp===""){
         createMessageBox('Please enter the prefix of the labels.', doNothing, false);
         $('#prefixId').val('');$('#prefixId').focus();return;
      }
      temp = $('#firstId').val();
      if(!validate(temp, /[a-z]{3,5}[0-9]{5,6}/i) || temp===undefined || temp===""){
         createMessageBox('Please enter the first label in the printed series.', doNothing, false);
         $('#firstId').val('');$('#firstId').focus();return;
      }
      temp = $('#lastId').val();
      if(!validate(temp, /[a-z]{3,5}[0-9]{5,6}/i) || temp===undefined || temp===""){
         createMessageBox('Please enter the last label in the printed series.', doNothing, false);
         $('#lastId').val('');$('#lastId').focus();return;
      }
      temp = $('#copiesId').val();
      if(isNaN(temp) || temp===undefined || temp===""){
         createMessageBox('Please enter the number of copies printed.', doNothing, false);
         $('#copiesId').val('');$('#copiesId').focus();return;
      }
      temp = $('#totalId').val();
      if(isNaN(temp) || temp===undefined || temp===""){
         createMessageBox('Please enter the total labels printed.', doNothing, false);
         $('#totalId').val('');$('#totalId').focus();return;
      }
      //things are now ok, so lets send the changes to the server
      var params='flag=save_settings&project='+encodeURIComponent($('#projectId').val())+'&prefix='+encodeURIComponent($('#prefixId').val());
      params+='&first='+encodeURIComponent($('#firstId').val())+'&last='+encodeURIComponent($('#lastId').val());
      params+='&copies='+encodeURIComponent($('#copiesId').val())+'&total='+encodeURIComponent($('#totalId').val());
      params+='&remarks='+encodeURIComponent($('#remarksId').val());

      Main.ajaxParams.successMssg='successfully saved.';
      Main.ajaxParams.div2Update='pl_printed';
      Main.ajaxParams.callFunction='Labels.closeSettings()';
      Main.initSorting = true;
      notificationMessage({create: true, hide:false, updatetext: false, text: 'saving settings...'});
      $.ajax({type:"POST", url:'seamless.php', data:params, dataType:'text', success:ajaxUpdateInterface});
   },

   closeSettings: function(){
      showHide('pl_settings', 'hide');
   },

   /**
    * Update the interface for the user to enter the purpose of the labels
    */
   labelsPurpose: function(){
      var purpose, content = '<legend>Purpose of the labels</legend>', projects = '', requester = '', settings, pId, charge_code;

      settings = {name: 'project', id: 'projectId', data: Main.projects, initValue: 'Select One'};
      projects = Common.generateCombo(settings);

      settings = {name: 'requester', id: 'requesterId', data: Main.users, initValue: 'Select One'};
      requester = Common.generateCombo(settings);

      $.each($('[name=purpose]'), function(){
         if(this.checked) purpose = this.value;
      });

      if(purpose == 'testing'){
         content += "Printing testing labels, nothing to save here!";
      }
      else{
         content +="<div>\n\
         <table>\n\
            <tr><td>Project</td><td><span id='project_place'>"+ projects +"</span></td></tr>\n\
            <tr><td>Requester</td><td><span id='requester_place'>"+ requester +"</span></td></tr>\n\
            <tr><td>Charge Code</td><td><span id='charge_code'>Select a project first</span></td></tr>\n\
            <tr><td>Comments</td><td><textarea cols='20' rows='3' name=comments></textarea></td></tr>\n\
         </table>\n\
        </div>";
      }
      $('#info').html(content);

      //if we want to add a new project, change the combo to a text box
      $('#projectId').live('change', function(){
         pId = $('#projectId').val();
         if(pId == 999){
            $('#project_place').html("<input type='text' name='project' id='projectId' size='10' /><a href='javascript:;' class='cancel'><img src='images/close.png' /></a>");
            $('#charge_code').html("<input type='text' name='new_charge_code' id='new_charge_code' size='10' />");
            $('#project_place .cancel').live('click', function(){
               settings = {name: 'project', id: 'projectId', data: Main.projects, initValue: 'Select One'};
               projects = Common.generateCombo(settings);
               $('#project_place').html(projects);
               $('#charge_code').html('<b>Select a project first</b>');
            })
         }
         else if($('#projectId')[0].type != 'select-one'){/*Leave everything as is to enter the charge code*/}
         else{
            //update the charge-code for the project
            charge_code = '<b>Select a project first</b>';
            $.each(Main.projects, function(){
               if(pId == this.id) charge_code = this.charge_code;
            });
            $('#charge_code').html('<b>'+ charge_code +'</b>');
         }
      });

      //if we wanna add a new person, change the combo to a text box and vice versa
      $('#requesterId').live('change', function(){
         if($('#requesterId').val() == 999){
            $('#requester_place').html("<input type='text' name='requester' id='requesterId' size='10' /><a href='javascript:;' class='cancel'><img src='images/close.png' /></a>");
            $('#requester_place .cancel').live('click', function(){
               settings = {name: 'requester', id: 'requesterId', data: Main.users, initValue: 'Select One'};
               requester = Common.generateCombo(settings);
               $('#requester_place').html(requester);
            })
         }
      });
   },

   /**
    * Update the interface for the user to enter the label sequence information
    */
   labelsSequence: function(){
      var content = '<legend>Sequence of the labels</legend>', sequence, prefix = '', settings;

      settings = {name: 'prefix', id: 'prefixId', data: Main.prefix, initValue: 'Select One'};
      prefix = Common.generateCombo(settings);

      $.each($('[name=sequence]'), function(){
         if(this.checked) sequence = this.value;
      });

      if(sequence == 'sequential'){
         content +="<div>\n\
         <table>\n\
            <tr><td>Prefix</td><td><span id='prefix_place'>"+ prefix +"</span></td></tr>\n\
            <tr><td>Count</td><td><input type='text' name='count' value='' size='5'></td></tr>\n\
         </table>\n\
        </div>";
      }
      else{
         content +="<div>\n\
            <div>\n\
               <div>An excel file should be saved as tab delimited text file. It should contain only the sample ids that are to be on the barcode. The data can be in one or more columns.</div>\n\
               <br />Input File: <input type='file' name='labels[]' value='' width='20' />\n\
            </div>\n\
              <input type='hidden' name='flag' value='' />\n\
            <iframe id='seamless_upload' name='seamless_upload' src='#' style='width:0;height:0;border:0px solid #fff;'></iframe>\n\
        </div>";
         $('[name=upload]')[0].target = 'seamless_upload';
      }
      $('#sequence').html(content);
   },

   generateLabels: function(){
      //check that we have all the data for generating the labels
      var purpose = '', sequence = '', duplicates = '';

      $.each($('[name=purpose]'), function(){
         if(this.checked) purpose = this.value;
      });

/**
      $.each($('[name=sequence]'), function(){
         if(this.checked) sequence = this.value;
      });
*/

      $.each($('[name=duplicates]'), function(){
         if(this.checked) duplicates = this.value;
      });

      //check that we have selected a label type
      if($("#labelTypesId").val() == 0){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select the type of labels that you want to print.', error:true});
         $('labelTypesId').focus();
         return false;
      }

/**
      //have we selected the label sequence?
      if(sequence == ''){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select the sequence of the labels that you want to print.', error:true});
         return false;
      }
*/

      //and the purpose of the labels
      if(purpose == ''){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select the purpose of the labels that you want to print.', error:true});
         return false;
      }

      //what about duplicates
      if(duplicates == ''){
         Notification.show({create:true, hide:true, updateText:false, text:'Please specify whether to check for duplicate labels.', error:true});
         return false;
      }

      //check that all the label sequence info is in order
//      if(LabelPrinter.confirmLabelsSequence(sequence) == false) return false;

      //check that the label purpose is well defined
      if(LabelPrinter.confirmLabelsPurpose(purpose) == false) return false;

      return true;
   },

   /**
    * Confirm that the label purpose is in order
    */
   confirmLabelsPurpose: function(purpose){
      var reg, project = $('#projectId').val(), requester = $('#requesterId').val();

      if(purpose == 'testing') return true;     //nothing to do here, go back to Mama
      else{
         reg = /[1-9]([0-9])?|[a-z]+/i
         if(reg.test(project) == false){
            if($('#projectId')[0].type == 'select-one'){
               Notification.show({create:true, hide:true, updateText:false, text:'Please select the project for the labels that you are printing.', error:true});
               return false;
            }
            else{
               Notification.show({create:true, hide:true, updateText:false, text:"Please enter the project intended for the labels.", error:true});
               return false;
            }
         }

         //check if we need to specify a charge code
         if($('#new_charge_code').length != 0){
            reg = /^[A-Z]{2}[0-9]{2}\-(NBO|ADD|HYD|DEL|IBD|IND)\-[A-Z0-9]{4,9}$/
            if(reg.test($('#new_charge_code').val().toUpperCase()) == false){
               Notification.show({create:true, hide:true, updateText:false, text:"Please enter the correct charge code for the new project.", error:true});
               return false;
            }
         }

         //lets confirm that we have the person who requested for the labels
         reg = /^[1-9]([0-9])?|[a-z\s]+$/i
         if(reg.test(requester) == false){
            if($('#requesterId')[0].type == 'select-one'){
               Notification.show({create:true, hide:true, updateText:false, text:'Please select the person who requested the labels.', error:true});
               return false;
            }
            else{
               Notification.show({create:true, hide:true, updateText:false, text:"Please enter the name of the person who requested the labels.", error:true});
               return false;
            }
         }
      }
      return true;
   },

   /**
    * Confirm that the label sequence data is in order
    */
   confirmLabelsSequence: function(sequence){
      var reg, prefix;

      if(sequence == 'sequential'){
         //we have to choose a prefix and provide the number of labels to be generated
         prefix = $('#prefixId').val();
         reg = /[1-9]([0-9])?|[a-z]{3,4}/i
         if(reg.test(prefix) == false){
            if($('#prefixId')[0].type == 'select-one'){
               Notification.show({create:true, hide:true, updateText:false, text:'Please select the prefix of the labels that you are printing.', error:true});
               return false;
            }
            else{
               Notification.show({create:true, hide:true, updateText:false, text:"Please enter the prefix of the labels that you are printing. The prefix can only be <b>3-4 characters</b>.", error:true});
               return false;
            }
         }
         //and how many labels are we pinting
         if($("[name=count]").val().trim() == ''){
            Notification.show({create:true, hide:true, updateText:false, text:'Please enter the number of labels that you want to print.', error:true});
            return false;
         }
      }
      else{
         //we have to upload a file with the labels
         if($("[type=file]").val()==""){
            Notification.show({create:true, hide:true, updateText:false, text:'Please select a tab delimited file with the labels to generate.', error:true});
            return false;
         }
		}
      return true;
   },

   deletePrintedLabels: function(){}
};
