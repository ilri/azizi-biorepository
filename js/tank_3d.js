/**
 * The constructur for the object. 
 * Calling this method will add things to the scene
 * 
 * @param {type} sector    The name is misleading. The object holds the data corresponding to
 *                         whatever is going to be zoomed into eg tank
 *                         Looks like 
 *                            {isTank: true, tankIndex:tankIndex, sectorIndex:i}
 *                            {isTank: false, data:searchGroupData}
 *                                     
 * @returns {Tank3D}
 */
function Tank3D(sector) {
   /*
    * Assuming that all the hard work has been done in Repository3D.
    * All we need to do here is:
    * -  add the internal components of the tank to the scene
    * -  create and add towers in the selected tower to the tank canvas
    */
   
   window.r3d.t3d = this;
   window.r3d.t3d.sector = sector;
   
   //if displaying all boxes in a sector then reset the tmp mta boxes and add boxes one by one again
   if(window.r3d.t3d.sector.isTank == true){
      window.r3d.resetMTABoxes();
   }
   
   jQuery(window.r3d.mtaBoxListDiv).hide();
   //window.r3d.tmp.mta_box = undefined;//TODO:might not work
   
   //create global variables
   
   //init event listener
   
   //init scene
   window.r3d.t3d.initScene();
   //set defaults in the scene
};

/**
 * This method adds objects to the already created scene
 * If data passed to this object corresponds to data on a tank
 * then items to add to the scene include:
 *    - the plane on which the towers sit
 *    - the towers with all their boxes
 * 
 * if data passed to this object corresponds to search results
 * then items to add to the scene include:
 *    - the plane on which the towers sit
 *    - the towers containing the search results (if search results are boxes)
 * @returns {undefined}
 */
Tank3D.prototype.initScene = function(){
   /*
    * What we need to init here is:
    * -  a plane where to place the towers
    * -  the towers
    */
   //console.log("init scene called");
   window.r3d.t3d.meshes = new Array();//everything that will be in this array will be removed from the scene when this object is deconstructed
   
   if(window.r3d.t3d.sector.isTank){
      var tankData = window.r3d.getTankData(window.r3d.t3d.sector.tankIndex);
   
      var sectorDataIndex = window.r3d.tanks[window.r3d.t3d.sector.tankIndex].sectors[window.r3d.t3d.sector.sectorIndex].dataIndex;
      if(tankData != null && typeof tankData.sectors[sectorDataIndex] != 'undefined' && tankData.sectors[sectorDataIndex] != null & tankData.sectors[sectorDataIndex].racks.length > 0){

         jQuery(window.r3d.zoomOutButton).html("Close");

         var sectorName = tankData.sectors[window.r3d.t3d.sector.sectorIndex].facility.slice(-1);

         //var towerPlane = new THREE.Mesh(new THREE.PlaneGeometry(6.5, 3.5), new THREE.MeshNormalMaterial({ transparent: true, opacity: 0.9, color: 0x00000000 }));
         var planeWidth = 6.5;
         var planeHeight = 3.5;
         var towerPlane = new THREE.Mesh(new THREE.PlaneGeometry(planeWidth, planeHeight), new THREE.MeshLambertMaterial({color: 0x3d3d3d, transparent : true, opacity : 0.8}));
         towerPlane.rotation.x = -Math.PI/2;
         towerPlane.position.y = 2;
         towerPlane.position.x = window.r3d.tanks[window.r3d.t3d.sector.tankIndex].mesh.position.x;
         towerPlane.position.z = window.r3d.tanks[window.r3d.t3d.sector.tankIndex].mesh.position.z;
         window.r3d.scene.add(towerPlane);
         window.r3d.t3d.meshes.push({type:"plane", mesh:towerPlane});

         //get the number of towers in the sector

         var towerNumber = tankData.sectors[window.r3d.t3d.sector.sectorIndex].racks.length;

         //add towers to tower plane
         for(var towerIndex = 0 ; towerIndex < towerNumber; towerIndex++){
            var tower = tankData.sectors[window.r3d.t3d.sector.sectorIndex].racks[towerIndex];
            window.r3d.t3d.createTower(tower, towerIndex, towerPlane, planeWidth, planeHeight, towerNumber);
         }
      }
      else{
         //console.log("No data in the database linked to the selected sector");
      }
   }
   else{
      if(!window.r3d.t3d.isActive()){
         //console.log("initializing to display search results");
         var searchGroups = window.r3d.t3d.sector.data;
         var towers = new Array();
         var noTowers = 0;
         for(var groupIndex = 0; groupIndex < searchGroups.length; groupIndex++) {
            if(searchGroups[groupIndex].type == "box"){
               for(var boxIndex = 0; boxIndex < searchGroups[groupIndex].extra.length; boxIndex++){
                  var boxDetails = searchGroups[groupIndex].extra[boxIndex];
                  var towerIndex = boxDetails.index[0] + "-" + boxDetails.index[1] + "-" + boxDetails.index[2];

                  if(typeof towers[towerIndex] == 'undefined'){
                     towers[towerIndex] = {
                        name: window.r3d.tankData.data[boxDetails.index[0]].sectors[boxDetails.index[1]].facility.replace(/sector.*/ig,'').replace(/tank/gi,'T') +
                                window.r3d.tankData.data[boxDetails.index[0]].sectors[boxDetails.index[1]].racks[boxDetails.index[2]].name,
                        boxes:[],
                        index:[boxDetails.index[0], boxDetails.index[1], boxDetails.index[2]]
                     };

                     noTowers++;
                  }

                  var boxData = window.r3d.tankData.data[boxDetails.index[0]].sectors[boxDetails.index[1]].racks[boxDetails.index[2]].boxes[boxDetails.index[3]];
                  //console.log(searchGroups[groupIndex].color);
                  boxData.color = searchGroups[groupIndex].color;
                  towers[towerIndex].boxes.push(boxData);
               }
            }
         }


         var planeWidth = 6.5;
         var planeHeight = 3.5;
         var towerPlane = new THREE.Mesh(new THREE.PlaneGeometry(planeWidth, planeHeight), new THREE.MeshLambertMaterial({color: 0x3d3d3d, transparent : true, opacity : 0.8}));
         towerPlane.rotation.x = -Math.PI/2;
         towerPlane.position.y = 2;
         towerPlane.position.x = window.r3d.tmp['lookAt'].x;
         towerPlane.position.z = window.r3d.tmp['lookAt'].z;
         window.r3d.scene.add(towerPlane);
         window.r3d.t3d.meshes.push({type:"plane", mesh:towerPlane});
         
         //add towers to tower plane
         //console.log("number of towers = " + noTowers);
         var towerIndex = 0;
         for(var ti in towers){
            window.r3d.t3d.createTower(towers[ti], towerIndex, towerPlane, planeWidth, planeHeight, noTowers);
            towerIndex++;
         }
         
         setTimeout(window.r3d.moveCameraToObject(towerPlane, false, null, 6.5), 500);
      }
   }
};

/**
 * This method is used for sending the scene to a previous state
 * e.g   - zooming out to previous state
 *       - hide boxes and towers
 * 
 * @returns {undefined}
 */
Tank3D.prototype.clear = function(){
   //window.r3d.tmp.mta_box = undefined;
   jQuery(window.r3d.mtaBoxListDiv).hide();
   
   jQuery(window.r3d.statsBox).hide();
   jQuery(window.r3d.virtBox).hide();
   
   //if at box level, zoom out to sector/tank level
   if(window.r3d.t3d.isAtBoxLevel()){
      if(window.r3d.t3d.sector.isTank){
         var target = {
            x : window.r3d.tanks[window.r3d.t3d.sector.tankIndex].mesh.position.x,
            y : window.r3d.tanks[window.r3d.t3d.sector.tankIndex].mesh.position.y,
            z : window.r3d.tanks[window.r3d.t3d.sector.tankIndex].mesh.position.z
         };
         
         window.r3d.resetCamera(target, 6);
         jQuery(window.r3d.zoomOutButton).html("Close");
      }
      else {
         //console.log("lookat looks like ", window.r3d.tmp['lookAt']);
         
         var target = {
            x : window.r3d.tmp['lookAt'].x,
            y : window.r3d.tmp['lookAt'].y,
            z : window.r3d.tmp['lookAt'].z
         };
         
         window.r3d.resetCamera(target, 6);
      }
      window.r3d.t3d.clearBoxLabels();
   }
   
   else {
      for(var i = 0; i < window.r3d.t3d.meshes.length; i++){
         window.r3d.scene.remove(window.r3d.t3d.meshes[i].mesh);
      }
      jQuery(window.r3d.zoomOutButton).html("Zoom Out");
      window.r3d.t3d.meshes = new Array();
      
      /*if(window.r3d.t3d.sector.isTank == true){//if we are in tank mode, reset tmp mta boxes
         window.r3d.tmp.mta_boxes = new Array();
         console.log("clearing tmp MTA boxes from reset function");
      }*/
      
      if(!window.r3d.t3d.sector.isTank) {//if zooming out from search results, just zoom out completely
         //console.log("called from tank3d");
         window.r3d.resetCamera();
      }
   }
};

/**
 * This method adds a tower to the scene
 * 
 * @param {type} towerData    The data corresponding to the tower in window.r3d.tankData.data[tankindex].sectors[sectorindex].racks array
 * @param {type} index        The index of the tower in window.r3d.tankData.data[tankindex].sectors[sectorindex].racks
 * @param {type} plane        The plane on which the tower sits
 * @param {type} planeWidth   The width of the plane
 * @param {type} planeHeight  The height of the plane
 * @param {type} towerNumber  The number of towers to be placed on the plane
 * 
 * @returns {undefined}
 */
Tank3D.prototype.createTower = function(towerData, index, plane, planeWidth, planeHeight, towerNumber){
   //console.log("createTower called");
   
   if(towerData.name.length > 5){
      var labelTexture = new THREEx.DynamicTexture(120, 60);
      labelTexture.context.font	= "bolder 30px Roboto";
      labelTexture.clear('black');
      labelTexture.drawText(towerData.name, undefined, 40, 'white');
      var labelGeometry = new THREE.PlaneGeometry(0.8, 0.2);
   }
   else {
      var labelTexture = new THREEx.DynamicTexture(512, 512);
      labelTexture.context.font	= "bolder 200px Roboto";
      labelTexture.clear('black');
      labelTexture.drawText(towerData.name, undefined, 310, 'white');
      var labelGeometry = new THREE.CircleGeometry(0.25, 64);
   }
   
   var labelMaterial = new THREE.MeshBasicMaterial({
      map : labelTexture.texture,
      color : 0xd3d3d3
   });
   
   var labelMesh = new THREE.Mesh(labelGeometry, labelMaterial);
   labelMesh.overdraw = true;
   
   var resolution = 2/10;//ratio between the number of towers on width with the number of towers on height
   var noRows = Math.ceil(Math.sqrt(resolution * towerNumber));
   var rowSize = Math.ceil(towerNumber/noRows);
   var rowNumber = window.r3d.t3d.calcRowNumber(index, rowSize);
   var columnNumber = window.r3d.t3d.calcColumnNumber(index, rowSize);
   //console.log("********************************************");
   //console.log("index : " + index);
   //console.log("rowNumber : " + rowNumber);
   //console.log("columnNumber : " + columnNumber);
   
   var trueX = plane.position.x - planeWidth/2;
   var trueZ = plane.position.z - planeHeight/2;
   
   var maxRows = window.r3d.t3d.calcRowNumber(towerNumber - 1, rowSize) + 1;
   var scale = 0.3;
   var towerX = trueX + (columnNumber * (planeWidth/rowSize)) + planeWidth/(2*rowSize);//*scale;
   var towerZ = trueZ + (rowNumber * (planeHeight/maxRows)) + planeHeight/(2*maxRows);//*scale;
   
   var towerLoader = new THREE.JSONLoader();
   towerLoader.load('js/models/tank_tower.json', function(geometry) {
      var material = new THREE.MeshLambertMaterial({color: 0xd3d3d3});
      mesh = new THREE.Mesh(geometry, material);
      
      mesh.scale.x = scale;
      mesh.scale.y = scale;
      mesh.scale.z = scale;
      mesh.rotation.x = -Math.PI/2;
      mesh.rotation.y = -Math.PI/2;
      mesh.position.x = towerX;
      mesh.position.z = towerZ + 0.3;
      mesh.position.y = plane.position.y + 0.1;
      
      labelMesh.scale.x = scale;
      labelMesh.scale.y = scale;
      labelMesh.scale.z = scale;
      labelMesh.rotation.x = -Math.PI/2;
      labelMesh.position.x = mesh.position.x;
      labelMesh.position.y = mesh.position.y;
      labelMesh.position.z = mesh.position.z + 0.1;
      
      window.r3d.scene.add(mesh);
      window.r3d.scene.add(labelMesh);
      window.r3d.t3d.meshes.push({type:"tower",mesh:mesh,data:towerData});
      window.r3d.t3d.meshes.push({type:"label",mesh:labelMesh});
      ////console.log(box.min, box.max, box.size());
      var boxes = towerData.boxes;
      for(var boxIndex = 0; boxIndex < boxes.length; boxIndex++){
         window.r3d.t3d.createBox(mesh, boxes[boxIndex]);
      }
   });
};

/**
 * This method adds a box in it's displayed tower
 * 
 * @param {type} towerMesh    The THREE.mesh corresponding to the tower where you want to put the box
 * @param {type} boxData      Data corresponding to the box in window.r3d.tankData.data[tankindex].sectors[sectorindex].racks[rackindex].boxes array
 * 
 * @returns {undefined}
 */
Tank3D.prototype.createBox = function(towerMesh, boxData){
   var boxRetrieved = false;
   for(var retrieveIndex = 0; retrieveIndex < boxData.retrieves.length; retrieveIndex++){
      if(boxData.retrieves[retrieveIndex].date_returned == null || boxData.retrieves[retrieveIndex].date_returned == "null"){
         boxRetrieved = true;
         break;
      }
   }
   
   var color = 0x056f00;
   if(boxRetrieved) color = 0xff3d00;
   
   if(typeof  boxData.color != 'undefined') color = parseInt(boxData.color.replace("#","0x"), 16);
   
   //console.log(color);
   
   var boxPosition = parseInt(boxData.rack_position);
   var towerMeshDimens = new THREE.Box3().setFromObject(towerMesh);
   
   var boxHeight = towerMeshDimens.size().z/13;
   var boxWidth = towerMeshDimens.size().x;
   var boxBredth = towerMeshDimens.size().y;
   
   var trueTowerZ = towerMeshDimens.min.z;
   
   var z = trueTowerZ + (boxHeight/2) + ((boxPosition - 1) * boxHeight);
   
   var boxGeometry = new THREE.BoxGeometry(boxWidth - 0.01, boxBredth, boxHeight - 0.01);
   for(var faceIndex = 0; faceIndex < boxGeometry.faces.length; faceIndex++){
      boxGeometry.faces[faceIndex].color.setHex(0x259b24);
   }
   var boxMesh = new THREE.Mesh(boxGeometry, new THREE.MeshLambertMaterial({color : color}));
   boxMesh.overdraw = true;
   boxMesh.position.x = towerMesh.position.x + 0.0075;
   boxMesh.position.y = towerMesh.position.y;
   boxMesh.position.z = z;
   boxMesh.rotation.x = (-Math.PI);
   window.r3d.scene.add(boxMesh);
   //console.log(boxData);
   
   //add box to mta_boxes
   if(window.r3d.t3d.sector.isTank == true){//only push boxes if we are in tank mode and not search mode
      window.r3d.addToMTABoxes(boxData.box_id, boxData.box_name);
      
      console.log(".");
   }
   
   window.r3d.t3d.meshes.push({type:"box", mesh:boxMesh, data:boxData});
};

/**
 * This method calculates the row number for a tower in the plane
 * 
 * @param {type} index     The index of the tower in the list of towers
 * @param {type} rowSize   The size of a row in the plane
 * 
 * @returns {Number}
 */
Tank3D.prototype.calcRowNumber = function(index, rowSize) {
   var iIndex = index + 1;
   if((iIndex % rowSize) === 0){//this is the last element in the row
      return ((iIndex/rowSize) - 1);
   }
   else{
      var x = iIndex/rowSize;
      var stepArray = x.toString().split(".");
      return parseInt(stepArray[0]);
   }
};

/**
 * This method calculates the column number for a tower in the plane
 * 
 * @param {type} index     The index of the tower in the list of towers
 * @param {type} rowSize   The size of the row in the plane
 * 
 * @returns {unresolved}
 */
Tank3D.prototype.calcColumnNumber = function(index, rowSize){
   return index % rowSize;
};

/**
 * This method checks whether this object is currently displaying anything in the 
 * scene
 * 
 * @returns {Boolean} TRUE if something generated by this object is being displayed in the scene
 */
Tank3D.prototype.isActive = function() {
   if(window.r3d.t3d.meshes.length > 0){
      return true;
   }
   else {
      return false;
   }
};

/**
 * This method handles click events on a rack/tower
 * 
 * @param {type} raycaster    THREE.Raycaster used to determine that the tower was clicked
 * 
 * @returns {undefined}
 */
Tank3D.prototype.onRackClicked = function(raycaster){
   //console.log("onRackClicked called");
   window.r3d.t3d.clearBoxLabels();
   var clickedBoxes = new Array();//stores indexes of boxes in t3d.meshes array that have been clicked
   for(var meshIndex  = 0; meshIndex < window.r3d.t3d.meshes.length; meshIndex++){
      if(window.r3d.t3d.meshes[meshIndex].type == "box"){//mesh is a box
         //test for intersects hence clicks
         var intersects = raycaster.intersectObject(window.r3d.t3d.meshes[meshIndex].mesh);
         if(intersects.length > 0){
            clickedBoxes.push(meshIndex);
         }
      }
   }
   ////console.log(clickedBoxes);
   //if multiple boxes clicked, zoom in to general area
   if (clickedBoxes.length == 1){
      //console.log("**************************");
      //console.log(clickedBoxes);
      //console.log(window.r3d.t3d.meshes[clickedBoxes[0]]);
      //console.log("##########################");
      jQuery(window.r3d.zoomOutButton).html("Zoom Out");
      
      for(var mi = 0; mi < window.r3d.t3d.meshes.length; mi++){
         if (window.r3d.t3d.meshes[mi].type == "box"){
            if(mi == clickedBoxes[0]){
               window.r3d.t3d.showBoxLabel(window.r3d.t3d.meshes[mi], true);
               //console.log(window.r3d.t3d.meshes[mi].data);
            }
            else {
               if(window.r3d.t3d.meshes[mi].data.rack == window.r3d.t3d.meshes[clickedBoxes[0]].data.rack){
                  ////console.log("match match match");
                  window.r3d.t3d.showBoxLabel(window.r3d.t3d.meshes[mi], false);
               }
            }
         }
      }
      
      if(typeof window.r3d.tmp.mta_box == 'undefined') window.r3d.tmp.mta_box = new Array();
      window.r3d.tmp.mta_box.push({id: window.r3d.t3d.meshes[clickedBoxes[0]].data.box_id, name: window.r3d.t3d.meshes[clickedBoxes[0]].data.box_name});
      
      window.r3d.moveCameraToObject(window.r3d.t3d.meshes[clickedBoxes[0]].mesh, false, null, 3.2);
      window.r3d.t3d.showBoxStatistics(window.r3d.t3d.meshes[clickedBoxes[0]].data);
      
      
   }
   else{
      //console.log("More than one box clicked");
   }
   
};

/**
 * This method creates a label for a box
 * 
 * @param {type} box       Object holding the mesh and data for the box
 * @param {type} inFocus   TRUE if the user just clicked this box
 * 
 * @returns {undefined}
 */
Tank3D.prototype.showBoxLabel = function(box, inFocus){
   var boxGeometry = new THREE.Box3().setFromObject(box.mesh);
   
   var labelTexture = new THREEx.DynamicTexture(512,280);
   labelTexture.context.font	= "bolder 70px Roboto";
   if(inFocus) labelTexture.clear('yellow');
   else labelTexture.clear('green');
   labelTexture.drawText(box.data.box_name, undefined, 170, 'black');
   var labelGeometry = new THREE.PlaneGeometry(boxGeometry.size().x, boxGeometry.size().z);
   var labelMaterial = new THREE.MeshBasicMaterial({
      map : labelTexture.texture,
      color : 0xd3d3d3
   });
   var labelMesh = new THREE.Mesh(labelGeometry, labelMaterial);
   labelMesh.overdraw = true;
   
   labelMesh.rotation.x = -Math.PI/2;
   labelMesh.position.x = box.mesh.position.x;
   labelMesh.position.y = box.mesh.position.y + (boxGeometry.size().y/2) + 0.001;
   labelMesh.position.z = box.mesh.position.z;
   
   window.r3d.t3d.meshes.push({type:"boxLabel", mesh:labelMesh});
   window.r3d.scene.add(labelMesh);
};

/**
 * This method removes all boxes from the scene
 * 
 * @returns {undefined}
 */
Tank3D.prototype.clearBoxLabels = function(){
   for(var index = 0; index < window.r3d.t3d.meshes.length; index++){
      if(window.r3d.t3d.meshes[index].type == "boxLabel"){
         window.r3d.scene.remove(window.r3d.t3d.meshes[index].mesh);
         window.r3d.t3d.meshes.splice(index, 1);
         index--;
      }
   }
};

/**
 * This method displayes data corresponding to a box
 * 
 * @param {type} boxData
 * @returns {undefined}
 */
Tank3D.prototype.showBoxStatistics = function(boxData) {
   jQuery(window.r3d.statsBox).show();
   jQuery(window.r3d.virtBox).hide();
   window.r3d.t3d.clearSampleVis();
   
   window.r3d.t3d.getBoxSamples(boxData, function(boxId, data){
      
      if(boxId == parseInt(boxData.box_id)){
         jQuery(window.r3d.statsBox).empty();
         
         var html = "<h1>"+ boxData.box_name +"</h1>";
         html = html +  "<h2><strong>Status: </strong>"+boxData.status+"</h2>";
         html = html +  "<h2><strong>Owner: </strong>"+window.r3d.getKeeperName(boxData.keeper)+"</h2>";
         html = html +  "<h2><strong>Date Added: </strong>"+boxData.date_added+"</h2>";
         
         if(typeof data != 'undefined' && data.length > 0){
            window.r3d.t3d.visualizeSamples(boxData.size, data);
            var origins = new Array();
            var material = new Array();
            
            for(var sampleIndex = 0; sampleIndex < data.length; sampleIndex++){
               if(jQuery.inArray(data[sampleIndex].origin, origins) == -1){
                  origins.push(data[sampleIndex].origin);
               }
               
               if(jQuery.inArray(data[sampleIndex].sample_type_name, material) == -1){
                  material.push(data[sampleIndex].sample_type_name);
               }
            }
            
            var originsLabel = "Origin";
            if(origins.length > 1) originsLabel = "Origins";
            
            html = html + "<h2><strong>Number of samples: </strong>"+data.length+"</h2>";
            html = html + "<h2><strong>" + originsLabel + ": </strong>"+origins.join(", ")+"</h2>";
            html = html + "<h2><strong>Material: </strong>" + material.join(", ") + "</h2>";
         }
         
         jQuery(window.r3d.statsBox).append(html);
      }
      else{
         //console.log("data out of sync");
      }
   });
};

/**
 * This method gets data corresponding to samples in a box
 * Note that some code in this method runs asynchronously
 * 
 * @param {type} boxData      Data corresponding to the box
 * @param {type} onComplete   Code that should be run after data has been gotten from the server
 * 
 * @returns {window.r3dtmp.box_samples|Window.r3dtmp.box_samples}
 */
Tank3D.prototype.getBoxSamples = function(boxData, onComplete) {
   
   var boxId = parseInt(boxData.box_id);
   
   if(typeof window.r3d.tmp['box_samples'] == 'undefined') window.r3d.tmp['box_samples'] = new Array();
   
   var async = true;
   if(typeof onComplete == 'undefined') async = false;
   
   var uri = window.r3d.serverURI + 'get_box_samples';
   if(typeof window.r3d.tmp['box_samples'][boxId] == 'undefined') {
      jQuery.ajax({
         url: uri,
         type: 'POST',
         async: async,
         data: {"box_id": boxId}
      }).done(function(data){
         window.r3d.tmp['box_samples'][boxId] = jQuery.parseJSON(data);
         
         if(async == false) return window.r3d.tmp['box_samples'][boxId];
         else onComplete(boxId, window.r3d.tmp['box_samples'][boxId]);
      });
   }
   
   if(async == false) return window.r3d.tmp['box_samples'][boxId];
   else onComplete(boxId, window.r3d.tmp['box_samples'][boxId]);
};

/**
 * This method returns true of labels for some boxes are currently being displayed
 * 
 * @returns {Boolean}   TRUE if labels for some boxes are currently being displayed
 */
Tank3D.prototype.isAtBoxLevel = function(){
   var atBoxLevel = false;
   
   for(var index = 0; index < window.r3d.t3d.meshes.length; index++){
      if(window.r3d.t3d.meshes[index].type == "boxLabel"){
         atBoxLevel = true;
         break;
      }
   }
   
   return atBoxLevel;
};

/**
 * This method determines if this object is currently displaying data based on search results
 * 
 * @returns {Boolean}   TRUE if so
 */
Tank3D.prototype.isInSearchMode = function() {
   if(window.r3d.t3d.sector.isTank) return false;
   else return true;
};

/**
 * This method displayes samples in form of a box in the scene
 * 
 * @param {type} boxSize      The size o fthe box in LabCollector format
 * @param {type} sampleData   The data corresponding to the samples to be displayed
 * 
 * @returns {undefined}
 */
Tank3D.prototype.visualizeSamples = function(boxSize, sampleData){
   jQuery(window.r3d.virtBox).show();
   //console.log("visualizeSamples called");
   //console.log(sampleData);
   window.r3d.tmp['curr_box_samples'] = new Array();
   
   boxSize = window.r3d.t3d.getBoxSize(boxSize);
   
   var boxObject = jQuery(window.r3d.virtBox);
   boxObject.empty();
   var boxPosition = boxObject.position();
   
   var boxWidth = boxObject.width();
   var boxHeight = boxObject.height();
   
   var sampleMargin = 2;
   var sampleHeight = (boxHeight / Math.sqrt(boxSize));
   var sampleWidth = (boxWidth / Math.sqrt(boxSize));
   
   for(var i = 0; i < sampleData.length; i++){
      var lcPosition = sampleData[i].box_details;
      
      if(lcPosition != null && lcPosition != "null"){
         var  sampleIndex = window.r3d.t3d.getPosition(lcPosition, boxSize);
      
         var pHMargin = (sampleMargin * 100) / boxWidth;
         var pVMargin = (sampleMargin * 100) / boxHeight;

         var row = Math.floor(sampleIndex/Math.sqrt(boxSize));
         var column = sampleIndex % Math.sqrt(boxSize);

         /*var left = ((column * sampleWidth) + (sampleMargin/2) * 100)/boxWidth;
         left = left * 100;
         var top = ((row * sampleHeight) + (sampleMargin/2) * 100)/boxHeight;
         top = top * 100;*/

         var left = (column * 100) / Math.sqrt(boxSize) + pHMargin/2 ;
         var top = (row * 100)/ Math.sqrt(boxSize) + pVMargin/2;

         var currSample = jQuery("<div id='sample_"+sampleIndex+"'></div>");
         currSample.height(sampleHeight - sampleMargin);
         currSample.width(sampleWidth - sampleMargin);
         var sampleType = sampleData[i].sample_type;
         var color = "#000000";
         if(typeof sampleType != 'undefined') color = window.r3d.randomColor(parseInt(sampleType));

         currSample.css({top: top+'%', left: left+'%', position: 'absolute', background: color});
         currSample.html(lcPosition);
         
         //currSample.unbind('mouseenter').unbind('mouseleave');
         window.r3d.tmp['curr_box_samples'][currSample.attr('id')] = sampleData[i];
         currSample.mouseover(function(e){
            //console.log("mouseover for sample called");
            var tooltipLeft = e.pageX - jQuery(window.r3d.virtBox).width();
            var tooltipTop = e.pageY;
            jQuery(window.r3d.tooltip).css({left:tooltipLeft, top:tooltipTop});

            jQuery(window.r3d.tooltip).empty();
            jQuery(window.r3d.tooltip).show();
            
            var data = window.r3d.tmp['curr_box_samples'][this.id];
            var html = "<h1>"+data.box_details+" : "+data.SampleID+"</h1>";
            html = html + "<h2><strong>Sample type:</strong>" +data.sample_type_name + "</h2>";
            html = html + "<h2><strong>Organism:</strong>" + data.org_name + "</h2>";
            html = html + "<h2><strong>Origin:</strong>" +data.origin+ "</h2>";
            html = html + "<h2><strong>Date collected:</strong>" +data.date_created.replace(" 00:00:00",'')+ "</h2>";
            
            jQuery(window.r3d.tooltip).html(html);
            
         });

         boxObject.append(currSample);
      }
      else {
         //console.log("This sample does not have a position ");
         //console.log(sampleData[i]);
      }
   }
   
   jQuery(window.r3d.virtBox).mouseover(function(){
      if(window.r3d.tmp['curr_box_samples'].length > 0) {
         jQuery(window.r3d.tooltip).show();
      }
   });
   jQuery(window.r3d.virtBox).mouseout(function(){
      jQuery(window.r3d.tooltip).empty();
      jQuery(window.r3d.tooltip).hide();
   });
};

/**
 * This method hides the virtual sample box displaying positions of samples
 * 
 * @returns {undefined}
 */
Tank3D.prototype.clearSampleVis = function() {
   var boxObject = jQuery(window.r3d.virtBox);
   boxObject.empty();
};

/**
 * This method converts the position of a sample from the lab collector format
 * to a 0 based index position
 * 
 * @return {Number} The position of the sample
 */
Tank3D.prototype.getPosition = function(lcPosition, boxSize){
   var rowSize = Math.sqrt(parseInt(boxSize));
   
   var rowReg = /[a-z]+/ig;
   var colReg = /[0-9]+/g;
   
   var rowChar = "";
   var colNum = -1;
   
   rowChar = rowReg.exec(lcPosition)[0].toLowerCase();
   
   colNum = colReg.exec(lcPosition)[0];
   
   if(rowChar.length == 1 && colNum != -1){
      var rowIndex = parseInt(rowChar.charCodeAt(0) - 97);
      var columnIndex = parseInt(colNum) - 1;
      var position = (rowIndex * rowSize) + columnIndex;
      
      return position;
   }
   //console.log("something's wrong with "+lcPosition+" or "+boxSize);
   return -1;
};

/**
 * This method converts the size of a box from the lims format to a number
 * 
 * @param {type} limsSize     Size of the box in lims format
 * @returns {Tank3D.prototype.getBoxSize.posParts|Number}
 */
Tank3D.prototype.getBoxSize = function(limsSize) {
   if(limsSize != null && limsSize != "null"){
      var limsDimensions = limsSize.split(".");

      //you only need to process the last part of the size ie J:10
      var lastPos = limsDimensions[1];
      var posParts = lastPos.split(":");
      var asciiPart1  = posParts[0].charCodeAt(0) - 64;
      return asciiPart1 * posParts[1];
   }
   else return 100;
};

/**
 * This method returns the number of visible boxes in the scene
 * 
 * @returns {Number}    The number of visible boxes
 */
Tank3D.prototype.getNoVisibleBoxes = function() {
   var number = 0;
   for(var index = 0; index < window.r3d.t3d.meshes.length; index++){
      if(window.r3d.t3d.meshes[index].type == "box"){
         number++;
      }
   }
   
   //console.log("number of visible boxes ",number);
   return number;
};