/**
 * This is the constructor
 * @returns {undefined}
 */
function Parse() {
   window.parse = this;
   this.serverURL = "./modules/mod_parse_odk_backend.php";
   this.procFormOnServerURL = "mod_ajax.php?page=odk_parser&do=proc_odk_form";
   
   this.formOnServerID = $("#form_on_server").val();
   this.name = $("#name").val();
   this.email = $("#email").val();
   this.fileName = $("#file_name").val();
   this.parseType = $("#parseType").val();
   this.dwnldImages = $("#dwnldImages").val();
      
   //check if user selected form on server or provided files
   if($("#data_file_div").is(":visible") && $("#xml_file_div").is(":visible") ){//user provided files

      if(this.validateInput()) {
         var jsonFileRegex = /.+\.json$/i
         var csvFileRegex = /.+\.csv/i
         this.jsonText = "";
         this.csvText = "";
         if(jsonFileRegex.test($("#data_file").val()) === true){
             var jsonFile = document.getElementById("data_file").files[0];
             this.firstFileLoaded = false;
             this.readFile(jsonFile, "json");
         }
         else if(csvFileRegex.test($("#data_file").val()) === true){
             var csvFile = document.getElementById("data_file").files[0];
             this.firstFileLoaded = false;
             this.readFile(csvFile, "csv");
         }


         var xmlFile = document.getElementById("xml_file").files[0];
         this.xmlText = "";
         this.readFile(xmlFile, "xml");
      }
   }
   else{//user selected 
      if($("#form_on_server").val() !== ""){
         this.sendFormOnServerProcReq();
      }
      else{
         Notification.show({create:true, hide:true, updateText:false, text:'Please select a form', error:true});
         $("#form_on_server").focus();
      }
   }
}

Parse.prototype.readFile = function (file, output) {
   var fileReader = new FileReader();
   fileReader.onload = function(e) {
      var validOutput = false;
     if(output === "json") {
        var jsonString = e.target.result;
        jsonString = jsonString.split("}{").join("},{");
        jsonString = jsonString.split(/\r\n|\r|\n/g).join("");//remove all line breaks. IE, Opera use \r\n, safari might use \r and all else use \n
        //jsonString = jsonString.replace(/(\r\n|\n|\r)/gm, ""); //remove all line breaks
        if(jsonString.indexOf("[") !== 0){
            //console.log("no opening and closing square brackets");
            var opener = "[";
            jsonString = opener.concat(jsonString, "]");
        }
        validOutput = window.parse.validateJson(jsonString);
        window.parse.jsonText = jsonString;
     }
     else if(output === "xml") {
        window.parse.xmlText = e.target.result;
        validOutput = true;
     }
     else if(output === "csv") {
         window.parse.csvText = e.target.result;
         validOutput = true;
     }
     
     if(validOutput === true) {
         if (window.parse.firstFileLoaded === false) {
            window.parse.firstFileLoaded = true;
         }
         else {
            console.log("sending data to server");
            window.parse.sendToServer();
         }
     }
   };
   fileReader.readAsText(file);
};

Parse.prototype.sendToServer = function () {
  jQuery.ajax ({
      url: window.parse.serverURL,
      type: 'POST',
      async: true,
      data: {
         creator: window.parse.name,
         email: window.parse.email,
         fileName: window.parse.fileName,
         jsonString: window.parse.jsonText,
         xmlString: window.parse.xmlText,
         parseType: window.parse.parseType,
         dwnldImages: window.parse.dwnldImages,
         csvString: window.parse.csvText
      }
   });
   alert("It may take some time to process the files you have provided. An email with the excel file will be sent to the email you have provided when the processing is done");
   //location.reload();
};

Parse.prototype.sendFormOnServerProcReq = function() {
   jQuery.ajax({
      url: window.parse.procFormOnServerURL,
      type: 'POST',
      async: true,
      data: {
         creator: window.parse.name,
         email: window.parse.email,
         fileName: window.parse.fileName,
         formOnServerID: window.parse.formOnServerID,
         parseType: window.parse.parseType,
         dwnldImages: window.parse.dwnldImages,
      }
   });
   alert("It may take some time to process the ODK form specified. An email with the ouput file will be sent to the email you have provided when the processing is done");
};

Parse.prototype.validateInput = function () {
   var emailRegex = /\S+@\S+\.\S+/;
   
   if(window.parse.name === undefined || window.parse.name.length === 0) {
      Notification.show({create:true, hide:true, updateText:false, text:'Please enter Your name', error:true});
      $("#name").focus();
      return false;
   }
   if(window.parse.email === undefined || window.parse.email.length === 0) {
      Notification.show({create:true, hide:true, updateText:false, text:'Please enter your email address', error:true});
      $("#email").focus();
      return false;
   }
   else if(emailRegex.test(window.parse.email) === false) {
      Notification.show({create:true, hide:true, updateText:false, text:'The email you entered is invalid', error:true});
      $("#email").focus();
      return false;
   }
   if(window.parse.fileName === undefined || window.parse.fileName.length === 0) {
       Notification.show({create:true, hide:true, updateText:false, text:'Please enter the excel file name', error:true});
      $("#file_name").focus();
      return false;
   }
   if($("#data_file").val() === undefined || $("#data_file").val().length === 0) {
      Notification.show({create:true, hide:true, updateText:false, text:'Please select the JSON or CSV file to be parsed', error:true});
      $("#data_file").focus();
      return false;
   }
   if($("#xml_file").val() === undefined || $("#xml_file").val().length === 0) {
       Notification.show({create:true, hide:true, updateText:false, text:'Please select the XML file to be parsed', error:true});
      $("#xml_file").focus();
      return false;
   }
   return true;
};

Parse.prototype.validateJson = function (jsonString) {
   try {
     var jsonObject = JSON.parse(jsonString);
   }
   catch (error) {
      //alert("The JSON file provided is invalid");
      Notification.show({create:true, hide:true, updateText:false, text:'The JSON file you provided is invalid', error:true});
      $("#data_file").focus();
      return false;
   }
   return true;
};
