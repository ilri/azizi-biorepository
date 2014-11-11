/**
 * The constructor for this object
 * 
 * @returns {VisualizeSamples}
 */
function VisualizeSamples() {
   console.log("VisualizeSamples constructing");
   window.vs = this;//not a hack. Way around the ambigous nature of this keyword in javascript
   
   //initialize the html components to be used in this object
   window.vs.mapCanvas = jQuery("#map");
   window.vs.timeline = jQuery("#samples_timeline");
   window.vs.projectContainer = jQuery("#project_container");
   window.vs.projectList = jQuery("#project_list");
   window.vs.organismContainer = jQuery("#organism_container");
   window.vs.organismList = jQuery("#organism_list");
   window.vs.sampleTypesContainer = jQuery("#sample_types_container");
   window.vs.sampleTypesList = jQuery("#sample_types_list");
   window.vs.testContainer = jQuery("#test_container");
   window.vs.testList = jQuery("#test_list");
   window.vs.resultContainer = jQuery("#result_container");
   window.vs.resultList = jQuery("#result_list");
   window.vs.playButton = jQuery("#play_button");
   window.vs.stopButton = jQuery("#stop_button");
   window.vs.playSlider = jQuery("#play_slider");
   window.vs.headsUp = jQuery("#samples_heads_up");
   window.vs.emailDialog = jQuery("#email_dialog");
   window.vs.sendButton = jQuery("#send_button");
   window.vs.emailDialogToggle = jQuery("#email_dialog_toggle");
   window.vs.mtaLink = jQuery("#mta_link");
   window.vs.mtaDialog = jQuery("#mta_dialog");
   window.vs.mtaDialogToggle = jQuery("#mta_dialog_toggle");
   window.vs.mtaSubmiteButton = jQuery("#mta_submit_btn");
   
   window.vs.loadingDialog = jQuery("#loading_box");
   window.vs.loadingDialog.show();
   
   window.vs.assets = {
      toggleOff: "url(images/ic_action_cancel.png)",
      toggleOn: "url(images/ic_action_accept.png)",
      loading:"url(images/ic_action_loading.gif)",
      play:"url(images/ic_action_play.png)",
      pause:"url(images/ic_action_pause.png)",
      stop:"url(images/ic_action_stop.png)",
      download:"url(images/ic_action_download.png)"
   };
   
   jQuery(".filter_toggle").css("background-image", window.vs.assets.loading);
   window.vs.projectSelectAll = jQuery("#project_sel_all");
   window.vs.projectToggle = jQuery("#project_toggle");
   window.vs.organismSelectAll = jQuery("#organism_sel_all");
   window.vs.organismToggle = jQuery("#organism_toggle");
   window.vs.sampleTypesSelectAll = jQuery("#sample_types_sel_all");
   window.vs.sampleTypesToggle = jQuery("#sample_types_toggle");
   window.vs.sampleCountDialog = jQuery("#sample_count");
   window.vs.downloadStatus = jQuery("#samples_download_btn");
   window.vs.downloadStatus.css("background-image", window.vs.assets.download);
   window.vs.testSelectAll = jQuery("#test_sel_all");
   window.vs.testToggle = jQuery("#test_toggle");
   window.vs.resultSelectAll = jQuery("#result_sel_all");
   window.vs.resultToggle = jQuery("#result_toggle");
   window.vs.windowResized();//since here is where all the dynamic positions and sizes are set
   
   //initialize data objects
   window.vs.loading = 0;//this variable stores the progress of loading the different data sets ie (samples, projects, organisms, sampletypes)
   window.vs.data = {};
   window.vs.data.filterIn = new Array();//samples that have passed all filters
   window.vs.data.filterOut = new Array();//samples that failed at least one filter
   window.vs.data.downloadData = new Array();//samples that will be downloaded when user presses download button
   window.vs.data.onTimeline = {samples: new Array(), days:{}};//subset of filterIn samples that are currently being displayed in timeline
   window.vs.data.projects = new Array();
   window.vs.data.organisms = new  Array();
   window.vs.data.sampleTypes = new Array();
   window.vs.data.tests = new Array();
   window.vs.data.results = new Array();
   window.vs.data.tlBounds = {floor:-1, ceiling:-1};
   window.vs.data.playInterval = -1;
   window.vs.filterWebWorker = -1;
   
   window.vs.filters = {projects:[], organisms:[], sampleTypes:[], tests:[], results:[]};
   
   window.vs.layers = {};
   window.vs.serverURI = "mod_ajax.php?page=samples_vis&do=ajax&action=";
   window.vs.getAllSampleData();
   window.vs.getAllProjectData();
   window.vs.getAllOrganismData();
   window.vs.getAllSampleTypeData();
   
   //initialize the map
   window.vs.initMap();
   /*window.vs.getGeoLocation(function(position) {
      window.vs.setMapCenter(position);
   });*/
   
   //create handlers
   //handler for window resize
   jQuery(window).resize(window.vs.windowResized);
   jQuery("#project_label").click(function(){
      window.vs.toggleProjects();
   });
   jQuery("#organism_label").click(function (){
      window.vs.toggleOrganisms();
   });
   jQuery("#sample_types_label").click(function (){
      console.log("sample types clicked");
      window.vs.toggleSampleTypes();
   });
   jQuery("#test_label").click(function (){
      console.log("test clicked");
      window.vs.toggleTests();
   });
   jQuery("#result_label").click(function (){
      console.log("results clicked");
      window.vs.toggleResults();
   });
   
   //initialize the toggle handlers
   window.vs.projectToggle.click(function(){
      window.vs.toggleProjects();
   });
   
   window.vs.projectSelectAll.change(function(){
      var filter = true;
      if(window.vs.filters.projects.length == window.vs.data.projects.length) filter = false;
      
      if(filter == true) window.vs.projectToggle.css("background-image", window.vs.assets.loading);
      
      window.vs.filters.projects = new Array();
      
      if(jQuery(this).is(":checked")){//user just added all projects to filter list
         for(var index = 0; index < window.vs.data.projects.length; index++){
            window.vs.filters.projects.push(window.vs.data.projects[index].val_id);
            jQuery("#project_" + window.vs.data.projects[index].val_id).prop("checked", true);
         }
      }
      else {
         for(var index = 0; index < window.vs.data.projects.length; index++){
            jQuery("#project_" + window.vs.data.projects[index].val_id).prop("checked", false);
         }
      }
      
      if(filter == true) window.vs.filter();
   });
   
   window.vs.organismToggle.click(function(){
      window.vs.toggleOrganisms();
   });
   
   window.vs.organismSelectAll.change(function(){
      var filter = true;
      if(window.vs.filters.organisms.length == window.vs.data.organisms.length) filter = false;
      
      if(filter == true) window.vs.organismToggle.css("background-image", window.vs.assets.loading);
      
      window.vs.filters.organisms = new Array();
      
      if(!jQuery(this).is(":checked")){//at least something in projects filter, clear everything
         for(var index = 0; index < window.vs.data.organisms.length; index++){
            jQuery("#organism_" + window.vs.data.organisms[index].org_id).prop("checked", false);
         }
      }
      else {
         for(var index = 0; index < window.vs.data.organisms.length; index++){
            window.vs.filters.organisms.push(window.vs.data.organisms[index].org_id);
            jQuery("#organism_" + window.vs.data.organisms[index].org_id).prop("checked", true);
         }
      }
      
      if(filter == true) window.vs.filter();
   });
   
   window.vs.sampleTypesToggle.click(function(){
      window.vs.toggleSampleTypes();
   });
   
   window.vs.sampleTypesSelectAll.change(function(){      
      var filter = true;
      if(window.vs.filters.sampleTypes.length == window.vs.data.sampleTypes.length) filter = false;

      if(filter == true) window.vs.sampleTypesToggle.css("background-image", window.vs.assets.loading);
      
      window.vs.filters.sampleTypes = new Array();
      
      if(!jQuery(this).is(":checked")){//at least something in projects filter, clear everything
         for(var index = 0; index < window.vs.data.sampleTypes.length; index++){
            jQuery("#sample_type_" + window.vs.data.sampleTypes[index].count).prop("checked", false);
         }
      }
      else {
         for(var index = 0; index < window.vs.data.sampleTypes.length; index++){
            window.vs.filters.sampleTypes.push(window.vs.data.sampleTypes[index].count);
            jQuery("#sample_type_" + window.vs.data.sampleTypes[index].count).prop("checked", true);
         }
      }
      
      if(filter == true) window.vs.filter();
   });
   
   window.vs.testToggle.click(function(){
      window.vs.toggleTests();
   });
   
   window.vs.testSelectAll.change(function(){
      window.vs.testToggle.css("background-image", window.vs.assets.loading);
      window.vs.filters.tests = new Array();
      
      if(!jQuery(this).is(":checked")){//at least something in projects filter, clear everything
         for(var index = 0; index < window.vs.data.tests.length; index++){
            jQuery("#test_" + window.vs.data.tests[index].replace(/[^a-z0-9]/gi, "_")).prop("checked", false);
         }
      }
      else {
         for(var index = 0; index < window.vs.data.tests.length; index++){
            window.vs.filters.tests.push(window.vs.data.tests[index]);
            jQuery("#test_" + window.vs.data.tests[index].replace(/[^a-z0-9]/gi, "_")).prop("checked", true);
         }
      }
      
      window.vs.filter();
   });
   
   window.vs.resultToggle.click(function(){
      window.vs.toggleResults();
   });
   
   window.vs.resultSelectAll.change(function(){
      window.vs.resultToggle.css("background-image", window.vs.assets.loading);
      window.vs.filters.results = new Array();
      
      if(!jQuery(this).is(":checked")){//at least something in projects filter, clear everything
         for(var index = 0; index < window.vs.data.results.length; index++){
            jQuery("#result_" + window.vs.data.results[index]).prop("checked", false);
         }
      }
      else {
         for(var index = 0; index < window.vs.data.results.length; index++){
            window.vs.filters.results.push(window.vs.data.results[index]);
            jQuery("#result_" + window.vs.data.results[index]).prop("checked", true);
         }
      }
      
      window.vs.filter();
   });
   
   //init events for the play button
   window.vs.stopButton.mouseup(function() {
      console.log("stop button clicked");
      window.vs.stopTimelinePlay(true);
   });
   window.vs.playButton.mouseup(function() {
      window.vs.play();
   });
   
   window.vs.headsUp.click(function() {
      if(window.vs.data.downloadData.length > 0 && window.vs.data.downloadData.length < 50000){
         window.vs.emailDialog.show();
      }
   });
   
   window.vs.sendButton.click(function() {
      window.vs.sendSampleData();
   });
   
   window.vs.emailDialogToggle.click(function() {
      window.vs.emailDialog.hide();
   });
   
   window.vs.mtaLink.click(function(){
      window.vs.emailDialog.hide();
      window.vs.mtaDialog.show();
   });
   
   window.vs.mtaDialogToggle.click(function(){
      window.vs.mtaDialog.hide();
   });
   
   window.vs.mtaSubmiteButton.click(function(){
      console.log("click");
      window.vs.submitMTARequest();
   });
};

/**
 * This method is called whenever the window is resized
 * @returns {undefined}
 */
VisualizeSamples.prototype.windowResized = function(){
   console.log("windowResized called");
   
   window.vs.loadingDialog.css("top", (window.innerHeight/2)-(window.vs.loadingDialog.height()/2)+"px");
   window.vs.loadingDialog.css("left", (window.innerWidth/2)-(window.vs.loadingDialog.width()/2)+"px");
   
   var labelHeight = jQuery("#project_label").height();
   var filterWidth = jQuery("#project_container").width();
   jQuery(".filter_toggle").css("height", labelHeight);
   jQuery(".filter_toggle").css("width", labelHeight);
   jQuery(".filter_label").css("width", filterWidth - labelHeight);
   
   var ctnrDist = window.vs.projectContainer.height() + 30;
   var ctnrTop = window.innerHeight * 0.1;
   
   window.vs.projectContainer.css("top", ctnrTop + "px");
   window.vs.projectContainer.css("left", (window.innerWidth * 0.05) + "px")
   window.vs.organismContainer.css("top", (ctnrDist + ctnrTop) + "px");
   window.vs.organismContainer.css("left", (window.innerWidth * 0.05) + "px")
   window.vs.sampleTypesContainer.css("top", (ctnrDist*2 + ctnrTop) + "px");
   window.vs.sampleTypesContainer.css("left", (window.innerWidth * 0.05) + "px")
   window.vs.testContainer.css("top", (ctnrDist*3 + ctnrTop) + "px");
   window.vs.testContainer.css("left", (window.innerWidth * 0.05) + "px")
   window.vs.resultContainer.css("top", (ctnrDist*4 + ctnrTop) + "px");
   window.vs.resultContainer.css("left", (window.innerWidth * 0.05) + "px")
   
   window.vs.timeline.css("top", (window.innerHeight - window.vs.timeline.height()) + "px");
   window.vs.playSlider.css("top", (window.innerHeight - window.vs.timeline.height()) + "px");
   
   window.vs.playButton.css("top", (window.innerHeight - window.vs.timeline.height() - window.vs.playButton.height() - 20) + "px");
   window.vs.stopButton.css("top", (window.innerHeight - window.vs.timeline.height() - window.vs.playButton.height() - 20) + "px");
   window.vs.stopButton.css("left", (window.vs.playButton.position().left + window.vs.playButton.width() + 25 )+"px");
   
   window.vs.headsUp.css("left", (window.innerWidth - window.vs.headsUp.width() - 100) + "px");
   
   window.vs.emailDialog.css("left", (window.innerWidth/2 - window.vs.emailDialog.width()/2) + "px");
   window.vs.emailDialog.css("top", (window.innerHeight/2 - window.vs.emailDialog.height()/2) + "px");
   
   window.vs.mtaDialog.css("left", (window.innerWidth/2 - window.vs.mtaDialog.width()/2) + "px");
   window.vs.mtaDialog.css("top", (window.innerHeight/2 - window.vs.mtaDialog.height()/2) + "px");
   
   /*var height = window.innerHeight;
   console.log("  window height = ", height);
   var width = window.innerWidth;
   console.log("  window width = ", width);*/
};

/**
 * This method initializes the map
 * 
 * @returns {undefined}
 */
VisualizeSamples.prototype.initMap = function(){
   console.log("initMap called");
   
   var location = [1.2833, 36.8167];//default location
   //init the map object
   window.vs.map = L.map(window.vs.mapCanvas[0].id, {
      center: location,
      zoom: 3
   });
   
   L.tileLayer( 'http://{s}.mqcdn.com/tiles/1.0.0/map/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="http://osm.org/copyright" title="OpenStreetMap" target="_blank">OpenStreetMap</a> contributors | Tiles Courtesy of <a href="http://www.mapquest.com/" title="MapQuest" target="_blank">MapQuest</a> <img src="http://developer.mapquest.com/content/osm/mq_logo.png" width="16" height="16">',
      subdomains: ['otile1','otile2','otile3','otile4']
   }).addTo( window.vs.map );
   
   window.vs.layers.heatmapLayer = L.heatLayer([],{
      radius:30
   }).addTo(window.vs.map);
};

VisualizeSamples.prototype.updateLoadingProgress = function(){
   window.vs.loading++;
   if(window.vs.loading === 4){//all data has been loaded
      window.vs.loadingDialog.hide();
      window.vs.filter();
   }
   else{
      window.vs.loadingDialog.html(window.vs.loadingDialog.html()+".");
   }
};

/**
 * This method fetches all samples with location data from the
 * server
 * Note that some of the code in this method runs asynchronously
 * 
 * @param {type} onComplete   Code to be executed once the data is gotten from the server
 * @returns {undefined}
 */
VisualizeSamples.prototype.getAllSampleData = function(onComplete){
   console.log("getAllSampleData called");
   var uri = window.vs.serverURI + "all_sample_data";
   
   jQuery.ajax({
      url: uri,
      type: 'POST',
      async: true
   }).done(function(data){
      var jsonObject = jQuery.parseJSON(data);
      console.log("************************************");
      console.log("sample data gotten from the server");
      console.log("x size of dataset = ",jsonObject.samples.length);
      window.vs.data.samples = jsonObject.samples;
      window.vs.data.tests = jsonObject.tests.types;
      window.vs.data.results = jsonObject.tests.results;
      
      for(var index = 0; index < window.vs.data.samples.length; index++ ){
         window.vs.data.filterIn.push(window.vs.data.samples[index]);
      }
      
      /*for(var index = 0; index < window.vs.data.tests.length; index++){
         window.vs.filters.tests.push(window.vs.data.tests[index]);
      }
      
      for(var index = 0; index < window.vs.data.results.length; index++){
         window.vs.filters.results.push(window.vs.data.results[index]);
      }*/
      
      window.vs.testToggle.css("background-image", "");
      window.vs.resultToggle.css("background-image", "");
      
      window.vs.updateLoadingProgress();
      
      if(typeof onComplete != 'undefined')  onComplete();
   });
};

VisualizeSamples.prototype.play = function() {
   //calculate the time represented by the width of the timeline
   if(window.vs.data.tlBounds.ceiling != -1 && window.vs.data.tlBounds.floor != -1){
      window.vs.stopButton.show();
      
      if(window.vs.data.playInterval == -1){//slider is not sliding at the moment. make it slide
         var tlWidth = window.vs.timeline.width();
         var timeDiff = window.vs.data.tlBounds.ceiling - window.vs.data.tlBounds.floor;//time difference in milliseconds

         //calculate the width of the slider
         /*
          * width of slider = 1 day if time difference < 3 month (30 times smaller)
          *                   1 week if time difference >= 3 month  < 1 year
          *                   1 month if time difference >= 1 year
          */

         var sliderWidth = -1;
         if(timeDiff < (3 * 30 * 86400000)) {
            console.log("slider reps 1 day");
            sliderWidth = (tlWidth * 86400000)/timeDiff;//slider will rep 1 day
         }
         else if(timeDiff >= (3 * 30 * 86400000) && timeDiff < (365 * 86400000)){
            console.log("slider reps 1 week");
            sliderWidth = (tlWidth * 86400000 * 7)/timeDiff;//slider will rep 1 week
         }
         else{
            console.log("slider reps 1 month");
            sliderWidth = (tlWidth * 86400000 * 30)/timeDiff;//slider will rep 1 month
         }

         if(sliderWidth != -1){
            window.vs.togglePlayButton();
            window.vs.playSlider.css("width", sliderWidth+"px");
            window.vs.playSlider.show();

            window.vs.data.playInterval = window.setInterval(function(){
               var left = window.vs.playSlider.position().left;
               
               //get time bounds represented by slider
               var floor = (window.vs.playSlider.position().left * (timeDiff/tlWidth)) + window.vs.data.tlBounds.floor;
               var ceiling = floor + (window.vs.playSlider.width() * (timeDiff/tlWidth));
               
               var samplesToShow = new Array();
               var allTLSamples = window.vs.data.onTimeline;
               var days = Object.keys(allTLSamples.days);
               
               for(var dayIndex = 0; dayIndex < days.length; dayIndex++){
                  if(parseInt(days[dayIndex]) >= floor && parseInt(days[dayIndex]) <= ceiling){
                     var sampleIndexes = allTLSamples.days[parseInt(days[dayIndex])];
                     for(var sampleIndex = 0; sampleIndex < sampleIndexes.length; sampleIndex++){
                        samplesToShow.push(allTLSamples.samples[sampleIndexes[sampleIndex]]);
                     }
                  }
               }
               
               window.vs.refreshHeatmap(samplesToShow, true);

               if(left >= (window.innerWidth - window.vs.playSlider.width())){
                  window.vs.stopTimelinePlay(true);
               }
               else {
                  left = left+1;
                  window.vs.playSlider.css("left", left+"px");
               }
            },10);
         }
      }
      else {
         window.vs.togglePlayButton();
      }
   }
};

VisualizeSamples.prototype.sendSampleData = function() {
   if(window.vs.data.downloadData.length > 0 && window.vs.data.downloadData.length < 50000){
      //get the box ids from downloadData array
      var sampleIDs = new Array();
      for(var sIndex = 0; sIndex < window.vs.data.downloadData.length; sIndex++){
         sampleIDs.push(window.vs.data.downloadData[sIndex].count);
      }

      console.log("sampleIDs = ", sampleIDs);
      
      var emailAddress = jQuery("#user_email").val();
      
      var emailRegex = /.+@.+\.[a-z0-9]+/i;
      
      if(emailAddress.match(emailRegex)){
         window.vs.emailDialog.hide();
         window.alert("Data on the samples will be sent to the email you provided");
         
         var uri = window.vs.serverURI + "send_sample_data";
         
         var data = {email:emailAddress, sampleIDs:sampleIDs};

         jQuery.ajax({
            url: uri,
            type: 'POST',
            data: data,
            async: true
         }).done(function(d){
            //TODO: do something to show the user the data is being sent
         });
      }
      else {
         
      }
   }
};

VisualizeSamples.prototype.togglePlayButton = function(){
   if(window.vs.data.playInterval != -1){//not playing at the moment
      window.clearInterval(window.vs.data.playInterval);
      window.vs.playButton.css("background-image", window.vs.assets.play);
      window.vs.playButton.css("background-position-x", "3px");
      window.vs.data.playInterval = -1;
   }
   else {
      window.vs.playButton.css("background-image", window.vs.assets.pause);
      window.vs.playButton.css("background-position-x", "0px");
   }
};

VisualizeSamples.prototype.stopTimelinePlay = function(refreshHeatmap) {
   if(window.vs.data.playInterval != -1){
      window.vs.togglePlayButton();
      window.vs.playSlider.hide();
      window.vs.playSlider.css("left", "0px");
      
      if(refreshHeatmap == true){
         window.vs.stopButton.hide();
         window.vs.refreshHeatmap(window.vs.data.onTimeline.samples);
      }
   }
   else {//currently not playing, might mean play has been paused but stop button was clicke
      if(refreshHeatmap == true){
         window.vs.data.playInterval = 0;
         window.vs.stopTimelinePlay(true);
      }
   }
};

VisualizeSamples.prototype.getDefaultHeatmap = function(){
   //var heatmapCoords = window.vs.getHeatmapCoords();
   
   //window.vs.layers.heatmapLayer.setData(heatmapCoords);
   
   var samplesData = window.vs.data.samples;
   for(var index = 0; index < samplesData.length; index++){
      //console.log(L.latLng(samplesData[index].Latitude, samplesData[index].Longitude));
      window.vs.data.filterIn.push(samplesData[index]);
      window.vs.layers.heatmapLayer._latlngs.push(new L.latLng(samplesData[index].Latitude, samplesData[index].Longitude));
   }
   
   window.vs.layers.heatmapLayer.redraw();
};

VisualizeSamples.prototype.getHeatmapCoords = function(){
   var heatmapCoords = new Array();
   var coordsArray = new Array();
   
   var samplesData = window.vs.data.samples;
   
   for(var index = 0; index < samplesData.length; index++){
      var heatIndex = jQuery.inArray([samplesData[index].Latitude, samplesData[index].Longitude], coordsArray);
      if(heatIndex == -1){//not in array
         heatmapCoords.push({lat:samplesData[index].Latitude, lon:samplesData[index].Longitude, count: 1});
         coordsArray.push([samplesData[index].Latitude, samplesData[index].Longitude]);
      }
      else {
         heatmapCoords[heatIndex].count = heatmapCoords[heatIndex].count++;
      }
   }
   
   return heatmapCoords;
};

/**
 * This method gets data on projects from the server
 * Note that some of the code in this function will run asynchronously from the
 * main thread
 * 
 * @returns {undefined}
 */
VisualizeSamples.prototype.getAllProjectData = function(onComplete) {
   var uri = window.vs.serverURI + "all_project_data";
   
   jQuery.ajax({
      url: uri,
      type: 'POST',
      async: true
   }).done(function(data){
      var jsonObject = jQuery.parseJSON(data);
      console.log("******************************");
      console.log("project data gotten from the server");
      console.log("x size of dataset = ",jsonObject.projects.length);
      window.vs.data.projects = jsonObject.projects;
      
      for(var index = 0; index < window.vs.data.projects.length; index++){
         window.vs.filters.projects.push(window.vs.data.projects[index].val_id);
      }
      
      window.vs.updateLoadingProgress();
      
      window.vs.projectToggle.css("background-image", "");
      
      if(typeof onComplete != 'undefined') onComplete();
   });
};

/**
 * This method gets data on organisms from the server
 * Note that some of the code in this function will run asynchronously from the
 * main thread
 * 
 * @returns {undefined}
 */
VisualizeSamples.prototype.getAllOrganismData = function(onComplete) {
   var uri = window.vs.serverURI + "all_organism_data";
   
   jQuery.ajax({
      url: uri,
      type: 'POST',
      async: true
   }).done(function(data){
      var jsonObject = jQuery.parseJSON(data);
      console.log("******************************");
      console.log("organism data gotten from the server");
      console.log("x size of dataset = ",jsonObject.organisms.length);
      window.vs.data.organisms = jsonObject.organisms;
      
      for(var index = 0; index < window.vs.data.organisms.length; index++) {
         window.vs.filters.organisms.push(window.vs.data.organisms[index].org_id);
      }
      
      window.vs.updateLoadingProgress();
      
      window.vs.organismToggle.css("background-image", "");
      
      if(typeof onComplete != 'undefined') onComplete();
   });
};

/**
 * This method gets data on sample types from the server
 * Note that some of the code in this function will run asynchronously from the
 * main thread
 * 
 * @returns {undefined}
 */
VisualizeSamples.prototype.getAllSampleTypeData = function(onComplete) {
   var uri = window.vs.serverURI + "all_sample_type_data";
   
   jQuery.ajax({
      url: uri,
      type: 'POST',
      async: true
   }).done(function(data){
      var jsonObject = jQuery.parseJSON(data);
      console.log("******************************");
      console.log("sample type data gotten from the server");
      console.log("x size of dataset = ",jsonObject.sample_types.length);
      window.vs.data.sampleTypes = jsonObject.sample_types;
      
      for(var index = 0; index < window.vs.data.sampleTypes.length; index++){
         window.vs.filters.sampleTypes.push(window.vs.data.sampleTypes[index].count);
      }
      
      window.vs.updateLoadingProgress();
      
      window.vs.sampleTypesToggle.css("background-image", "");
      
      if(typeof onComplete != 'undefined') onComplete();
   });
};

/**
 * This method adds projects from the server to the project list html element
 * 
 * @returns {undefined}
 */
VisualizeSamples.prototype.showSampleTypes = function() {
   var sampleTypes = window.vs.data.sampleTypes;
   
   if(sampleTypes.length > 0) {
      window.vs.sampleTypesSelectAll.parent().show();
   }
   
   for(var stIndex = 0; stIndex < sampleTypes.length; stIndex++) {
      
      var checked = "";
      if(jQuery.inArray(sampleTypes[stIndex].count, window.vs.filters.sampleTypes) != -1) checked = "checked";
      
      var stHTML = "<input type='checkbox' id='sample_type_"+sampleTypes[stIndex].count+"' "+checked+" />"+ sampleTypes[stIndex].sample_type_name + "<br />";
      console.log(stHTML);
      
      window.vs.sampleTypesList.append(stHTML);
      
      jQuery("#sample_type_"+sampleTypes[stIndex].count).change({stIndex:stIndex}, function(e){
         window.vs.sampleTypesToggle.css("background-image", window.vs.assets.loading);
         
         var stIndex = e.data.stIndex;
         var stId = window.vs.data.sampleTypes[stIndex].count;
         
         var checkFilterIn = false;
         
         if(this.checked == true){//add organimsId to filters
            window.vs.filters.sampleTypes.push(stId);
            if(window.vs.filters.sampleTypes.length == 1){
               checkFilterIn = true;
            }
         }
         else {
            checkFilterIn = true;
            window.vs.filters.sampleTypes.splice(jQuery.inArray(stId, window.vs.filters.sampleTypes),1);
         }
         
         window.vs.filter(checkFilterIn);
         //window.vs.filterOrganisms(organismIndex, this.checked);
      });
   }
};

/**
 * This method adds projects from the server to the project list html element
 * 
 * @returns {undefined}
 */
VisualizeSamples.prototype.showOrganisms = function() {
   var organisms = window.vs.data.organisms;
   
   if(organisms.length > 0) {
      window.vs.organismSelectAll.parent().show();
   }
   
   for(var organismIndex = 0; organismIndex < organisms.length; organismIndex++) {
      
      var checked = "";
      if(jQuery.inArray(organisms[organismIndex].org_id, window.vs.filters.organisms) != -1) checked = "checked";
      
      var organismHTML = "<input type='checkbox' id='organism_"+organisms[organismIndex].org_id+"' "+checked+" />"+ organisms[organismIndex].org_name + "<br />";
      
      window.vs.organismList.append(organismHTML);
      
      jQuery("#organism_"+organisms[organismIndex].org_id).change({organismIndex:organismIndex}, function(e){
         window.vs.organismToggle.css("background-image", window.vs.assets.loading);
         
         var organismIndex = e.data.organismIndex;
         var orgId = window.vs.data.organisms[organismIndex].org_id;
         
         var checkFilterIn = false;
         
         if(this.checked == true){//add organimsId to filters
            window.vs.filters.organisms.push(orgId);
            if(window.vs.filters.organisms.length == 1){
               checkFilterIn = true;
            }
         }
         else {
            checkFilterIn = true;
            window.vs.filters.organisms.splice(jQuery.inArray(orgId, window.vs.filters.organisms),1);
         }
         
         window.vs.filter(checkFilterIn);
         //window.vs.filterOrganisms(organismIndex, this.checked);
      });
   }
};

VisualizeSamples.prototype.showTests = function() {
   var tests = window.vs.data.tests;
   
   if(tests.length > 0){
      window.vs.testSelectAll.parent().show();
   }
   
   for(var testIndex = 0; testIndex < tests.length; testIndex++) {
      var checked = "";
      
      if(jQuery.inArray(tests[testIndex], window.vs.filters.tests) != -1) checked = "checked";
      
      var testHTML = "<input type='checkbox' id='test_"+tests[testIndex].replace(/[^a-z0-9]/gi, '_')+"' " + checked + " />"+ tests[testIndex] + "<br />";
      
      window.vs.testList.append(testHTML);
      
      jQuery("#test_"+tests[testIndex].replace(/[^a-z0-9]/gi, "_")).change({test:tests[testIndex]}, function(e) {
         window.vs.testToggle.css("background-image", window.vs.assets.loading);
         
         var test = e.data.test;
         
         if(this.checked == true){
            window.vs.filters.tests.push(test);
         }
         else {
            window.vs.filters.tests.splice(jQuery.inArray(test, window.vs.filters.tests),1);
         }
         
         window.vs.filter();
      });
   }
};

VisualizeSamples.prototype.showResults = function() {
   var results = window.vs.data.results;
   
   if(results.length > 0){
      window.vs.resultSelectAll.parent().show();
   }
   
   for(var resIndex = 0; resIndex < results.length; resIndex++) {
      var checked = "";
      
      if(jQuery.inArray(results[resIndex], window.vs.filters.results) != -1) checked = "checked";
      
      var resHTML = "<input type='checkbox' id='result_"+results[resIndex]+"' " + checked + " />"+ results[resIndex] + "<br />";
      
      window.vs.resultList.append(resHTML);
      
      jQuery("#result_"+results[resIndex]).change({result:results[resIndex]}, function(e) {
         window.vs.resultToggle.css("background-image", window.vs.assets.loading);
         
         var result = e.data.result;
         
         if(this.checked == true){
            window.vs.filters.results.push(result);
         }
         else {
            window.vs.filters.results.splice(jQuery.inArray(result, window.vs.filters.results),1);
         }
         
         window.vs.filter();
      });
   }
};

/**
 * This method adds projects from the server to the project list html element
 * 
 * @returns {undefined}
 */
VisualizeSamples.prototype.showProjects = function() {
   var projects = window.vs.data.projects;
   
   if(projects.length > 0) window.vs.projectSelectAll.parent().show();
   
   for(var projectIndex = 0; projectIndex < projects.length; projectIndex++) {
      
      var checked = "";
      
      if(jQuery.inArray(projects[projectIndex].val_id, window.vs.filters.projects) != -1) checked = "checked";
      
      var projectHTML = "<input type='checkbox' id='project_"+projects[projectIndex].val_id+"' " + checked + " />"+ projects[projectIndex].value + "<br />";
      
      window.vs.projectList.append(projectHTML);
      
      jQuery("#project_"+projects[projectIndex].val_id).change({projectIndex:projectIndex}, function(e){
         window.vs.projectToggle.css("background-image", window.vs.assets.loading);
         console.log(window.vs.assets.loading);
         
         var projectIndex = e.data.projectIndex;
         var projectId = window.vs.data.projects[projectIndex].val_id;
         
         var checkFilterIn = false;
         
         if(this.checked == true){
            window.vs.filters.projects.push(projectId);
            if(window.vs.filters.projects.length == 1){
               console.log("going to check filter in array");
               checkFilterIn = true;
            }
         }
         else {
            console.log("going to check filter in array");
            checkFilterIn = true;
            window.vs.filters.projects.splice(jQuery.inArray(projectId, window.vs.filters.projects),1);
         }
         
         window.vs.filter(checkFilterIn);
      });
   }
};

VisualizeSamples.prototype.filter = function(checkFilterIn) {
   if(typeof checkFilterIn == 'undefined') checkFilterIn == null;
   
   if(window.vs.filterWebWorker == -1){//means that there is no other async task running
      window.vs.loadingDialog.html("Filtering");
      window.vs.loadingDialog.show();
      var data = {
         filterIn: window.vs.data.filterIn,
         filterOut: window.vs.data.filterOut
      };

      var message = {
         data: data,
         filters: window.vs.filters,
         checkFilterIn: checkFilterIn
      };
      
      window.vs.filterWebWorker = new Worker("js/filter_samples_web_worker.js");
      
      if(typeof window.vs.filterWebWorker == 'undefined' || window.vs.filterWebWorker == null){
         window.alert("It looks like you are using a browser that does not support a core functionality of this site. Try using a different browser.");
         
         window.vs.loadingDialog.html("Download");
         window.vs.loadingDialog.hide();
      }
      else {
         window.vs.filterWebWorker.addEventListener('message', function(e){
            var jsonString = e.data;

            var data = jQuery.parseJSON(jsonString);

            window.vs.data.filterIn = data.data.filterIn;
            window.vs.data.filterOut = data.data.filterOut;

            window.vs.filters = data.filters;
            var histogram = data.histogram;

            console.log("histogram = ", histogram);

            window.vs.refreshHeatmap();
            window.vs.initTimeline(histogram);

            window.vs.resetFilterIcons();

            window.vs.filterWebWorker.terminate();
            window.vs.filterWebWorker = -1;

            window.vs.loadingDialog.html("Loading");
            window.vs.loadingDialog.hide();
         });

         window.vs.filterWebWorker.postMessage(JSON.stringify(message));
      }
   }
};

VisualizeSamples.prototype.resetFilterIcons = function() {
   if(window.vs.filters.projects.length == window.vs.data.projects.length){
      //window.vs.projectToggle.css("background-image", window.vs.assets.toggleOn);
      window.vs.projectSelectAll.prop("checked", true);
   }
   else {
      //window.vs.projectToggle.css("background-image", window.vs.assets.toggleOff);
      window.vs.projectSelectAll.prop("checked", false);
   }
   if(window.vs.projectList.children().length > 0){//showing projects
      window.vs.projectToggle.css("background-image", window.vs.assets.toggleOff);
   }
   else{
      window.vs.projectToggle.css("background-image", "");
   }

   if(window.vs.filters.organisms.length == window.vs.data.organisms.length){
      //window.vs.organismToggle.css("background-image", window.vs.assets.toggleOn);
      window.vs.organismSelectAll.prop("checked", true);
   }
   else {
      //window.vs.organismToggle.css("background-image", window.vs.assets.toggleOff);
      window.vs.organismSelectAll.prop("checked", false);
   }
   if(window.vs.organismList.children().length > 0){//showing projects
      window.vs.organismToggle.css("background-image", window.vs.assets.toggleOff);
   }
   else{
      window.vs.organismToggle.css("background-image", "");
   }

   if(window.vs.filters.sampleTypes.length == window.vs.data.sampleTypes.length){
      //window.vs.sampleTypesToggle.css("background-image", window.vs.assets.toggleOn);
      window.vs.sampleTypesSelectAll.prop("checked", true);
   }
   else {
      //window.vs.sampleTypesToggle.css("background-image", window.vs.assets.toggleOff);
      window.vs.sampleTypesSelectAll.prop("checked", false);
   }
   if(window.vs.sampleTypesList.children().length > 0){//showing projects
      window.vs.sampleTypesToggle.css("background-image", window.vs.assets.toggleOff);
   }
   else{
      window.vs.sampleTypesToggle.css("background-image", "");
   }
   
   if(window.vs.filters.tests.length == window.vs.data.tests.length){
      //window.vs.testToggle.css("background-image", window.vs.assets.toggleOn);
      window.vs.testSelectAll.prop("checked", true);
   }
   else {
      //window.vs.testToggle.css("background-image", window.vs.assets.toggleOff);
      window.vs.testSelectAll.prop("checked", false);
   }
   if(window.vs.testList.children().length > 0){//showing projects
      window.vs.testToggle.css("background-image", window.vs.assets.toggleOff);
   }
   else{
      window.vs.testToggle.css("background-image", "");
   }
   
   if(window.vs.filters.results.length == window.vs.data.results.length){
      //window.vs.resultToggle.css("background-image", window.vs.assets.toggleOn);
      window.vs.resultSelectAll.prop("checked", true);
   }
   else {
      //window.vs.resultToggle.css("background-image", window.vs.assets.toggleOff);
      window.vs.resultSelectAll.prop("checked", false);
   }
   if(window.vs.resultList.children().length > 0){//showing projects
      window.vs.resultToggle.css("background-image", window.vs.assets.toggleOff);
   }
   else{
      window.vs.resultToggle.css("background-image", "");
   }
};

VisualizeSamples.prototype.refreshHeatmap = function(data, playingTimeline){
   //console.log("refreshHeatmap called");
   
   window.vs.layers.heatmapLayer._latlngs = new Array();
   
   var samplesData = window.vs.data.filterIn;
   if(typeof data != 'undefined') samplesData = data;
   
   var sampleLabel = " Samples";
   if(samplesData.length == 1) sampleLabel = " Sample";
   
   window.vs.headsUp.show();
   window.vs.sampleCountDialog.html(samplesData.length + sampleLabel);
   window.vs.headsUp.css("left", (window.innerWidth - window.vs.headsUp.width() - 100) + "px");
   
   var maxRadius = 20;
   var allSamples = window.vs.data.filterIn.length + window.vs.data.filterOut.length;
   var radius = maxRadius * ((1/2)+(1-(samplesData.length/allSamples)));
   //console.log("heatmap radius = ", radius);
   
   window.vs.layers.heatmapLayer.setOptions({radius: radius});
   
   if(typeof playingTimeline == 'undefined' || playingTimeline == false) {
      window.vs.data.onTimeline.samples = samplesData;
      
      var days = {};
      for(var index = 0; index < samplesData.length; index++){
         var time = new Date(samplesData[index].date_created.split(" ")[0]).getTime();
         
         if(typeof days[time] == "undefined"){
            days[time] = new Array();
         }
         days[time].push(index);
      }
      
      window.vs.data.onTimeline.days = days;
   }//if we are showing only a subset of what is on the timeline no need for resetting onTimeline object
   
   for(var index = 0; index < samplesData.length; index++){
      //console.log(L.latLng(samplesData[index].Latitude, samplesData[index].Longitude));
      
      window.vs.layers.heatmapLayer._latlngs.push(new L.latLng(samplesData[index].Latitude, samplesData[index].Longitude));
      
//      if(index % 500 == 0) window.vs.layers.heatmapLayer.redraw();
   }
   
   window.vs.data.downloadData = samplesData;
   
   if(window.vs.data.downloadData.length > 50000 || window.vs.data.downloadData.length == 0){
      window.vs.downloadStatus.hide();
      window.vs.headsUp.attr("title", "You can only download data for up to 50,000 samples");
   }
   else {
      window.vs.downloadStatus.show();
      window.vs.headsUp.attr("title", "Download the data");
   }
   window.vs.headsUp.css("left", (window.innerWidth - window.vs.headsUp.width() - 100) + "px");
   
   window.vs.layers.heatmapLayer.redraw();
};

VisualizeSamples.prototype.hideProjectList = function(){
   window.vs.projectSelectAll.parent().hide();
   if(window.vs.projectToggle.css("background-image").indexOf("loading") == -1) window.vs.projectToggle.css("background-image", "");
   window.vs.projectList.empty();
   window.vs.projectContainer.css("z-index",2);
};

VisualizeSamples.prototype.hideOrganismList = function(){
   window.vs.organismSelectAll.parent().hide();
   if(window.vs.organismToggle.css("background-image").indexOf("loading") == -1) window.vs.organismToggle.css("background-image", "");
   window.vs.organismList.empty();
   window.vs.organismContainer.css("z-index",2);
};

VisualizeSamples.prototype.hideSampleTypeList = function(){
   window.vs.sampleTypesSelectAll.parent().hide();
   if(window.vs.sampleTypesToggle.css("background-image").indexOf("loading") == -1) window.vs.sampleTypesToggle.css("background-image", "");
   window.vs.sampleTypesList.empty();
   window.vs.sampleTypesContainer.css("z-index",2);
};

VisualizeSamples.prototype.hideTestList = function(){
   window.vs.testSelectAll.parent().hide();
   if(window.vs.testToggle.css("background-image").indexOf("loading") == -1) window.vs.testToggle.css("background-image", "");
   window.vs.testList.empty();
   window.vs.testContainer.css("z-index",2);
};

VisualizeSamples.prototype.hideResultList = function(){
   window.vs.resultSelectAll.parent().hide();
   if(window.vs.resultToggle.css("background-image").indexOf("loading") == -1) window.vs.resultToggle.css("background-image", "");
   window.vs.resultList.empty();
   window.vs.resultContainer.css("z-index",2);
};

VisualizeSamples.prototype.toggleProjects = function(){
   
   if(window.vs.data.projects.length > 0){
      var isVisible = true;
      if(jQuery("#project_"+window.vs.data.projects[0].val_id).length == 0) isVisible = false;
      
      if(isVisible == true) {
         window.vs.hideProjectList();
      }
      else {
         window.vs.projectToggle.css("background-image", window.vs.assets.toggleOff);
         window.vs.projectContainer.css("z-index",3);
         window.vs.showProjects();
         
         window.vs.hideOrganismList();
         window.vs.hideSampleTypeList();
         window.vs.hideTestList();
         window.vs.hideResultList();
      }
   }
};

VisualizeSamples.prototype.toggleOrganisms = function() {
   if(window.vs.data.organisms.length > 0){
      var isVisible = true;
      if(jQuery("#organism_"+window.vs.data.organisms[0].org_id).length == 0) isVisible = false;
      
      if(isVisible == true) {
         window.vs.hideOrganismList();
      }
      else {
         window.vs.organismToggle.css("background-image", window.vs.assets.toggleOff);
         window.vs.organismContainer.css("z-index",3);
         window.vs.showOrganisms();
         
         window.vs.hideProjectList();
         window.vs.hideSampleTypeList();
         window.vs.hideTestList();
         window.vs.hideResultList();
      }
   }
};

VisualizeSamples.prototype.toggleSampleTypes = function(){
   if(window.vs.data.sampleTypes.length > 0){
      var isVisible = true;
      if(jQuery("#sample_type_"+window.vs.data.sampleTypes[0].count).length == 0) isVisible = false;
      
      if(isVisible == true) {
         window.vs.hideSampleTypeList();
      }
      else {
         window.vs.sampleTypesToggle.css("background-image", window.vs.assets.toggleOff);
         window.vs.sampleTypesContainer.css("z-index",3);
         window.vs.showSampleTypes();
         
         window.vs.hideProjectList();
         window.vs.hideOrganismList();
         window.vs.hideTestList();
         window.vs.hideResultList();
      }
   }
};

VisualizeSamples.prototype.toggleTests = function(){
   if(window.vs.data.tests.length > 0){
      var isVisible = true;
      if(jQuery("#test_"+window.vs.data.tests[0].replace(/[^a-z0-9]/gi, "_")).length == 0) isVisible = false;
      
      if(isVisible == true) {
         window.vs.hideTestList();
      }
      else {
         window.vs.testToggle.css("background-image", window.vs.assets.toggleOff);
         window.vs.testContainer.css("z-index",3);
         window.vs.showTests();
         
         window.vs.hideProjectList();
         window.vs.hideOrganismList();
         window.vs.hideSampleTypeList();
         window.vs.hideResultList();
      }
   }
};

VisualizeSamples.prototype.toggleResults = function(){
   if(window.vs.data.results.length > 0){
      var isVisible = true;
      if(jQuery("#result_"+window.vs.data.results[0]).length == 0) isVisible = false;
      
      if(isVisible == true) {
         window.vs.hideResultList();
      }
      else {
         window.vs.resultToggle.css("background-image", window.vs.assets.toggleOff);
         window.vs.resultContainer.css("z-index",3);
         window.vs.showResults();
         
         window.vs.hideProjectList();
         window.vs.hideOrganismList();
         window.vs.hideSampleTypeList();
         window.vs.hideTestList();
      }
   }
};

VisualizeSamples.prototype.submitMTARequest = function(){
   var emailRegex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
   
  if($("#mta_pi_name").val().length == 0){
     window.alert("Please enter the Principal Investigator's name");
     $("#mta_pi_name").focus();
     return false;
  }
  else if($("#mta_pi_name").val().split(/\s+/).length < 2){
     window.alert("Please provide the Principal Investigator's two names");
     $("#mta_pi_name").focus();
     return false;
  }
  if($("#mta_pi_email").val().length == 0){
     window.alert("Please enter the Principal Investigator's email address");
     $("#mta_pi_email").focus();
     return false;
  }
  else if(emailRegex.test($("#mta_pi_email").val()) != true){
     window.alert("Email address provided is wrong");
     $("#mta_pi_email").focus();
     return false;
  }
  if($("#mta_research_title").val().length == 0){
     window.alert("Please provide the research title");
     $("#mta_research_title").focus();
     return false;
  }
  if($("#mta_org").val().length == 0){
     window.alert("Please provide your Organisation's name");
     $("#mta_org").focus();
     return false;
  }
  if($("#mta_material").val().length == 0){
     window.alert("Please enter the type of sample material");
     $("#mta_material").focus();
     return false;
  }
  if($("#mta_format").val().length == 0){
     window.alert("Please provide a format");
     $("#mta_format").focus();
     return false;
  }
  if($("#mta_assoc_data").val().length == 0){
     window.alert("What associated data do you need?");
     $("#mta_assoc_data").focus();
     return false;
  }
  
  //check if any samples selected
  if(window.vs.data.filterIn.length == 0){
     window.alert("No samples selected");
     return false;
  }
  
  //if we've come this far, data's validated and fine
  jQuery.ajax({
     url:'mod_ajax.php?page=mta&do=process_mta',
     method:'POST',
     async:true,
     data:{
        pi_name:$("#mta_pi_name").val(),
        pi_email:$("#mta_pi_email").val(),
        research_title:$("#mta_research_title").val(),
        org:$("#mta_org").val(),
        material:$("#mta_material").val(),
        format:$("#mta_format").val(),
        storage_safety:$("#mta_storage_safety").val(),
        assoc_data:$("#mta_assoc_data").val(),
        filters:window.JSON.stringify(window.vs.filters)
     },
     success:function(){
        window.vs.mtaDialog.hide();
        window.alert("Your request has been successfully submitted");
     }
  });
  return true;
};

VisualizeSamples.prototype.initTimeline = function(histogram){
   console.log("initTimeline called");
   
   var plot = function(){
      var histKeys = Object.keys(histogram);
   
      histKeys.sort(function(a, b){
         if(parseInt(a) > parseInt(b)) return 1;
         else if(parseInt(a) < parseInt(b)) return -1;
         else return 0;
      });//sort keys in ascending order. Remember keys are unix timestamps
      
      if(histKeys.length > 0){
         window.vs.data.tlBounds.floor = parseInt(histKeys[0]);
         window.vs.data.tlBounds.ceiling = parseInt(histKeys[histKeys.length-1]);
      }
      else {
         window.vs.data.tlBounds.floor = -1;
         window.vs.data.tlBounds.ceiling = -1;
      }
      
      var plotData = "x,y\n";
      for(var index = 0; index < histKeys.length; index++){
         var date = new Date(parseInt(histKeys[index]));
         plotData = plotData + date.toString() + "," + histogram[parseInt(histKeys[index])] + "\n";
      }

      // This function draws bars for a single series. See
         // multiColumnBarPlotter below for a plotter which can draw multi-series
         // bar charts.


      var graphObject = new Dygraph(window.vs.timeline[0], plotData,{
         drawGrid: false,
         drawYAxis: false,
         plotter: function(e){
            var ctx = e.drawingContext;
            var points = e.points;
            var y_bottom = e.dygraph.toDomYCoord(0);

            // The RGBColorParser class is provided by rgbcolor.js, which is
            // packed in with dygraphs.
            /*var color = new RGBColorParser(e.color);
            color.r = Math.floor((255 + color.r) / 2);
            color.g = Math.floor((255 + color.g) / 2);
            color.b = Math.floor((255 + color.b) / 2);*/
            ctx.fillStyle = "#006064";

            // Find the minimum separation between x-values.
            // This determines the bar width.
            var min_sep = Infinity;
            for (var i = 1; i < points.length; i++) {
              var sep = points[i].canvasx - points[i - 1].canvasx;
              if (sep < min_sep) min_sep = sep;
            }
            var bar_width = Math.floor(2.0 / 3 * min_sep);

            // Do the actual plotting.
            for (var i = 0; i < points.length; i++) {
              var p = points[i];
              var center_x = p.canvasx;

              ctx.fillRect(center_x - bar_width / 2, p.canvasy,
                  bar_width, y_bottom - p.canvasy);

              ctx.strokeRect(center_x - bar_width / 2, p.canvasy,
                  bar_width, y_bottom - p.canvasy);
            }
         },
         xAxisLabelFormatter: function(d, gran) {
            var months=new Array("Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec");
            
            if(gran > 14){
               return months[d.getMonth()] + Dygraph.zeropad(d.getYear() - 100, 1);
            }
            else {
               return d.getDate() + "-" +months[d.getMonth()] + "-" + Dygraph.zeropad(d.getYear() - 100, 1);
            }
         },
         axisLabelFontSize: 12,
         axisLabelWidth: 70,
         axisLabelColor: "#006064",
         zoomCallback: function(minDate, maxDate, yRanges) {
            console.log("ZoomCallback called");
            window.vs.stopTimelinePlay(false);//stop timeline play if running but do not refresh heatmap
            
            //minDate and maxDate are in the form of unix timestamp e.g 1286346300156.658
            
            //go through all the items in window.vs.data.filterIn and add the ones that lie in the range to the heatmap
            window.vs.data.tlBounds.floor = minDate;
            window.vs.data.tlBounds.ceiling = maxDate;
            
            var dataInRange = new Array();
            window.vs.data.timelineZoomed = false;
            for(var index = 0; index < window.vs.data.filterIn.length; index++){
               var date = new  Date(window.vs.data.filterIn[index].date_created.split(" ")[0]);//get only the date and discard the time
               if(date.getTime() >= minDate && date.getTime() <= maxDate){
                  dataInRange.push(window.vs.data.filterIn[index]);
               }
               else {
                  window.vs.data.timelineZoomed = true;
               }
            }
            
            window.vs.refreshHeatmap(dataInRange);
         },
         labels:["", "No. Samples"],
         valueFormatter: function(d, options, dygraph){
            if(d.toString().length == 13){//means that this is probably unix timestamp
               var date = new Date(d);
               var dateSuf = "th ";
               
               if(date.getDate().toString().slice(-1) == "1" && (date.getDate().toString().length == 1 || date.getDate().toString().charAt(0) == "2")) dateSuf = "st ";
               else if(date.getDate().toString().slice(-1) == "2" && (date.getDate().toString().length == 1 || date.getDate().toString().charAt(0) == "2")) dateSuf = "nd ";
               else if(date.getDate().toString().slice(-1) == "3" && (date.getDate().toString().length == 1 || date.getDate().toString().charAt(0) == "2")) dateSuf = "rd ";
               
               var months=new Array("Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec");
               return date.getDate() + dateSuf + months[date.getMonth()] + " " + (date.getYear() + 1900);
            }
            return d;
         },
         clickCallback: function(e, x, points){
            
            var clickHandler = function () {
               if(window.vs.playSlider.is(":visible") == true){
                  var x = e.x;

                  if(x > (window.vs.playSlider.width()/2)) x = x - (window.vs.playSlider.width()/2);

                  window.vs.playSlider.css("left", x+"px");
               }
            };
            
            if(window.vs.data.timelineZoomed == true){
               window.setTimeout(clickHandler, 200);//give the user enough time to double click
            }
            else {
               clickHandler();
            }
         }
      });
      
      console.log("graphObject = ", graphObject);
   };
   
   
   //execution of this method actually starts here
   window.vs.timeline.empty();
   
   var tHeight = window.innerHeight * 0.075;
   
   if(window.vs.data.filterIn.length == 0){
      window.vs.timeline.animate({
         height: "0px",
         top: window.innerHeight + "px",
         background: "#e0f7fa"
      }, 400, "swing", function(){
         window.vs.timeline.hide();
         window.vs.playButton.hide();
         window.vs.playSlider.hide();
      });
   }
   else {
      if(window.vs.timeline.is(":visible")) {
         plot();
      }
      else {//timeline is not showing. means that there was nothing to plot the last time
         window.vs.timeline.show();
         
         window.vs.playButton.css("top", (window.innerHeight - tHeight - window.vs.playButton.height() - 20) + "px");
         window.vs.stopButton.css("top", (window.innerHeight - tHeight - window.vs.playButton.height() - 20) + "px");
         window.vs.playSlider.css("top", (window.innerHeight - tHeight)+"px");
         window.vs.playSlider.css("height", tHeight+"px");
         window.vs.playButton.show();
         
         window.vs.timeline.animate({
            height: tHeight + "px",
            top: (window.innerHeight - tHeight)+"px",
            background: "#e0f7fa"
         }, 400, "swing", plot);
      }
   }
   
};
