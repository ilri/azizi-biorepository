var Main = {
   ajaxParams: {successMssg: undefined, div2Update: undefined}, successMssg: undefined, title: 'Label Printing', theme: ''
};

var LimsUploader={

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

    /**
     * Fetches the current sample sheet.
     */
    sampleSheet: function(){
       var module;
       $.each($('[name=module]'), function(){
          if(this.checked) module = this.value;
       });

      Main.ajaxParams.successMssg='Successfully fetched.';
      Main.ajaxParams.div2Update='right_panel';
      Main.initSorting = true;

      params='page=lims_uploader&do=sample_sheet&action='+ module;
      Notification.show({create: true, hide:false, updatetext: false, text: 'fetching data ...'});
      $.ajax({type:"GET", url:'mod_ajax.php', data:params, dataType:'json', success:Common.updateUserInterface});
    },

    /**
     * Downloads a template for the selected module
     */
    downloadSampleSheet: function(){
       var module;
       $.each($('[name=module]'), function(){
          if(this.checked) module = this.value;
       });

       $.download("mod_ajax.php?page=lims_uploader&do=download&action="+ module, {} , 'POST');
    },

    /**
     * Creates a panel fro uploading of the excel spreadsheet with the data to process
     */
    uploadingPanel: function(){
       var content = "<form enctype='multipart/form-data' name='upload' action='index.php?page=lims_uploader&do=process' method='POST' onSubmit='LimsUploader.processData();'>\n\
         <div class='instructions'>Please upload an excel spreadsheet with a format as shown.</div>\n\
         <div id='upload'>Input File: <input type='file' name='data[]' value='' width='20' /><input type='hidden' name='module' value='' /></div>\n\
         <div class='links'><input type='submit' name='submit' value='Upload' />&nbsp;<input type='button' name='cancel' value='cancel' /></div>\n\
         </form>";

       $('#right_panel').html(content);
    },

    /**
     * Ensure that a spreadsheet is uploaded before we start the processing actions
     */
    processData: function(){
      //we have to upload a file with the labels
      if($("[type=file]").val()==""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select an excel file with the data to processs.', error:true});
         return false;
      }
       var module;
       $.each($('[name=module]'), function(){
          if(this.checked) module = this.value;
       });
       $('[name=module]').val(module);
      return true;
    },

    confirmUpload: function(){
       var params = 'page=lims_uploader&do=post_data&action='+ this.value +'&file='+ $('[name=uploadedFile]').val() +'&module='+ $('[name=curModule]').val();
       window.location = 'index.php?'+ params;
    }
};