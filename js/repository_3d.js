
/**
 * The constructor for this class (Repository3D)
 * @returns {undefined}
 */
function Repository3D(inTemplate) {
   
   //console.log("Constructor called");
   window.r3d = this;//allows for referencing of class methods and global variables in a less hacky way
   
   var WIDTH = window.innerWidth;
   var HEIGHT = window.innerHeight;
   
   window.r3d.loadingBox = document.getElementById("loading_box");
   window.r3d.loadingBox.style.left = (WIDTH/2 - (jQuery(window.r3d.loadingBox).width()/2));
   window.r3d.loadingBox.style.top = (HEIGHT/2 - (jQuery(window.r3d.loadingBox).height()/2));
   jQuery(window.r3d.loadingBox).css("z-index", 6);
   
   //set up global variables
   window.r3d.serverURI = "mod_ajax.php?page=repository_3d&do=ajax&action=";
   
   //get tank details from server
   window.r3d.getAllTankData();//code here will run asynchronously
   
   window.r3d.tanks = new Array();
   window.r3d.floorWidth = 15;
   window.r3d.floorLength = 20;
   window.r3d.tmp = {};
   window.r3d.mtaFinalList = new Array();//final list of boxes to be submitted for an MTA
   
   window.r3d.tmp['mta_boxes'] = new Array();//an array that will store IDs of boxes that are going to be considered when filling in the MTA
   window.r3d.tmp['mta_box'] = undefined;//object to store details of a box if said box is zeroed in by the user
   
   /*
    * window.r3d.tmp['mta_boxes'] modified when user either searches and boxes are gotten or narrowes down to a specific set of boxes
    */
   
   //create the scene
   window.r3d.scene = new THREE.Scene();
   
   //set position of reset button
   window.r3d.zoomOutButton = document.getElementById("reset_button");
   window.r3d.zoomOutButton.style.left = (WIDTH - 140) + 'px';
   
   //init the search canvas
   window.r3d.searchCanvas = document.getElementById("search_canvas");
   window.r3d.searchBox = document.getElementById("search_box_3d");
   window.r3d.tmp['search'] = {query: "", results:[]};
   
   //init other views in the window that will be used
   window.r3d.statsBox = document.getElementById("stats_box");
   window.r3d.statsBox.style.left = (WIDTH -  jQuery(window.r3d.statsBox).width() - 100) + "px";
   window.r3d.statsBox.style.top = (HEIGHT * 0.6 ) + "px";
   window.r3d.virtBox = document.getElementById("virt_box");
   window.r3d.virtBox.style.left = (WIDTH -  jQuery(window.r3d.virtBox).width() - 100) + "px";
   window.r3d.virtBox.style.top = ((HEIGHT * 0.6 ) - jQuery(window.r3d.virtBox).height() - 30) + "px";
   window.r3d.tooltip = document.getElementById("sample_ttip");
   
   //init the small zoom in and out buttons
   window.r3d.zoomOut = document.getElementById("zoom_out");
   window.r3d.zoomOut.style.left = (WIDTH - 80) + 'px';
   window.r3d.zoomOut.style.top = 90 + 'px';
   
   window.r3d.zoomIn = document.getElementById("zoom_in");
   window.r3d.zoomIn.style.left = (WIDTH - 130) + 'px';
   window.r3d.zoomIn.style.top = 90 + 'px';
   
   //initialize views to be used for handling mta
   window.r3d.addBoxMTAButton = document.getElementById("add_box_mta_btn");
   window.r3d.addBoxMTAButton.style.left = jQuery(window.r3d.zoomOutButton).position().left - (50 + jQuery(window.r3d.addBoxMTAButton).width())+"px";
   jQuery(window.r3d.addBoxMTAButton).hide();
   
   window.r3d.mtaDialog = document.getElementById("mta_dialog");
   window.r3d.mtaDialog.style.left = (window.innerWidth/2 - jQuery(window.r3d.mtaDialog).width()/2)+ "px";
   window.r3d.mtaDialog.style.top = (window.innerHeight/2 - jQuery(window.r3d.mtaDialog).height()/2)+ "px";

   window.r3d.mtaDialogToggle = document.getElementById("mta_dialog_toggle");
   window.r3d.mtaButton = document.getElementById('mta_btn');
   window.r3d.mtaButton.style.left = jQuery(window.r3d.zoomOutButton).position().left - (jQuery(window.r3d.mtaButton).width() + 100 + jQuery(window.r3d.addBoxMTAButton).width())+"px";
   jQuery(window.r3d.mtaButton).hide();
   //window.r3d.mtaButton.style.top = 125+'px';
   window.r3d.mtaSubmitButton = document.getElementById('mta_submit_btn');
   window.r3d.mtaBoxSubmitButton = document.getElementById('mta_box_submit_btn');//button in confirm boxes dialog
   
   window.r3d.mtaBoxListDiv = document.getElementById("mta_box_list_div");
   window.r3d.mtaBoxListDiv.style.left = (window.innerWidth/2 - jQuery(window.r3d.mtaBoxListDiv).width()/2) + 'px';
   window.r3d.mtaBoxListDiv.style.top = (window.innerHeight/2 - jQuery(window.r3d.mtaBoxListDiv).height()/2) + 'px';
   window.r3d.mtaBoxList = document.getElementById("mta_box_list");
   window.r3d.mtaBoxListToggle = document.getElementById("mta_box_list_toggle");

   window.r3d.loadingBox = document.getElementById("loading_box");
   window.r3d.loadingBox.style.left = (WIDTH/2 - (jQuery(window.r3d.loadingBox).width()/2));
   window.r3d.loadingBox.style.top = (HEIGHT/2 - (jQuery(window.r3d.loadingBox).height()/2));
   jQuery(window.r3d.loadingBox).show();
   
   window.r3d.clearSearch = document.getElementById("clear_search");
   window.r3d.clearSearch.style.left = window.r3d.searchBox.style.left + jQuery(window.r3d.searchBox).width();
   /*var searchBoxHeight = jQuery(window.r3d.searchBox).height();
   jQuery(window.r3d.clearSearch).width(searchBoxHeight);
   jQuery(window.r3d.clearSearch).height(searchBoxHeight);
   jQuery(window.r3d.clearSearch).css('line-height', searchBoxHeight);*/
   
   //create the projector (still don't know what this is)
   window.r3d.projector = new THREE.Projector();

   //create a renderer
   try {
      window.r3d.renderer = new THREE.WebGLRenderer({antialias:true});
   }
   catch (error){
      window.r3d.renderer = new THREE.CanvasRenderer({antialias:true});
      window.alert("Your browser either does not support WebGL or has it turned off. The experience on this site will therefore be less than par. Try turning on WebGL or using another browser.");
   }
   
   if(typeof window.r3d.renderer != 'undefined'){
      window.r3d.renderer.setSize(WIDTH, HEIGHT);
      window.r3d.renderer.setClearColor(0xe2ecb8, 1);
      window.r3d.renderer.shadowMapEnabled = true;
      window.r3d.renderer.shadowMapCullFace = THREE.CullFaceBack;
      window.r3d.container = document.getElementById("repo_container");
      window.r3d.container.appendChild(window.r3d.renderer.domElement);

      //create a camera
      window.r3d.camera = new THREE.PerspectiveCamera(50, WIDTH/HEIGHT, 0.1, 2000);
      window.r3d.camera.position.set(0,15,20);
      window.r3d.tmp['lookAt'] = {x : 0, y : 0, z : 1};
      window.r3d.camera.lookAt(window.r3d.tmp['lookAt']);
      window.r3d.scene.add(window.r3d.camera);


      //init event listeners
      jQuery(window.r3d.clearSearch).click(function () {
         jQuery(window.r3d.searchBox).val('');
         jQuery(window.r3d.searchCanvas).empty();
         jQuery(window.r3d.clearSearch).hide();
         
         window.r3d.resetMTABoxes();
      });
      jQuery(window.r3d.searchBox).keyup(window.r3d.onSearchChange);

      window.addEventListener('resize', window.r3d.onDocumentResize);

      window.r3d.container.addEventListener('mousedown', window.r3d.onDocumentMouseDown, false);

      jQuery(window.r3d.zoomIn).click(function (){
         window.r3d.zoom(-1);
      });

      jQuery(window.r3d.zoomOut).click(function () {
         window.r3d.zoom(1);
      });

      //init the scene
      window.r3d.initScene();
      window.r3d.animate();

      //set default positions for camera
      window.r3d.tmp['defaultCP'] = { x: window.r3d.camera.position.x, y: window.r3d.camera.position.y, z: window.r3d.camera.position.z};
      window.r3d.tmp['defaultCLA'] = window.r3d.tmp['lookAt'];
      window.r3d.zoomOutButton.addEventListener('mousedown', function () {
         //console.log("zoom button clicked");
         window.r3d.handleZoomButtonEvent();
      }, false);
      
      jQuery(window.r3d.mtaButton).click(function(){
         window.r3d.populateMTABoxList();
         jQuery(window.r3d.mtaBoxListDiv).show();
      });
      
      jQuery(window.r3d.addBoxMTAButton).click(function(){
         jQuery(window.r3d.mtaButton).show();
         //check if user wants to add a single box or multiple boxes
         window.r3d.mtaFinalList = new Array();
         
         if(typeof window.r3d.tmp.mta_box == 'undefined'){//user probably wants to add a bunch of boxes
            for(var i = 0; i < window.r3d.tmp.mta_boxes.length; i++){//add the to mtaFinalList one by one to prevent js from creating pointer
               window.r3d.mtaFinalList.push({id:window.r3d.tmp.mta_boxes[i].id, name:window.r3d.tmp.mta_boxes[i].name});
            }
         }
         else {
            window.r3d.mtaFinalList.push({id:window.r3d.tmp.mta_box.id, name:window.r3d.tmp.mta_box.name});
         }
      });
      
      jQuery(window.r3d.mtaBoxListToggle).click(function(){
         console.log("click");
         jQuery(window.r3d.mtaBoxListDiv).hide();
      });
      
      jQuery(window.r3d.mtaBoxSubmitButton).click(function(){
         jQuery(window.r3d.mtaDialog).show();
      });
      jQuery(window.r3d.mtaDialogToggle).click(function(){
         jQuery(window.r3d.mtaDialog).hide();
      });
      jQuery(window.r3d.mtaSubmitButton).click(function(){
         window.r3d.processMTARequest();
      });
   }
   else {
      window.alert("A problem occurred while trying to initializing the rendering engine. Try accessing this site using a different browser.");
   }
   
   if(WIDTH < 1280){
      window.alert("This site will look and work better if you view it on a bigger screen");
   }
};

/**
 * This method is responsible for initializing the scene. Things done here include
 *    - setting the bounds of the scene
 *    - setting the floor
 *    - adding controls to the screeen
 * @returns {undefined}
 */
Repository3D.prototype.initScene = function() {
   //console.log("initScene called");
   //add lighting
   window.r3d.loadLighting();
   
   var xEdge = 3.5;//distance between the edge of the floor and the position of the tank in the x axis (width)
   var z1 = 5;
   var z2 = 1;
   var z3 = -3;
   var z4 = -5;
   
   //add all the tanks
   window.r3d.createTank(1, (window.r3d.floorWidth / 2) -xEdge, z1);
   
   window.r3d.createTank(2, (window.r3d.floorWidth / 2) - xEdge, z2);
   
   window.r3d.createTank(3, (window.r3d.floorWidth / 2) - xEdge, z3);
   
   window.r3d.createTank(4, 0, z4);
   
   window.r3d.createTank(6, -((window.r3d.floorWidth / 2) - xEdge), z3);
   
   window.r3d.createTank(7, -((window.r3d.floorWidth / 2) - xEdge), z2);
   
   //floor
   var floor = new THREE.Mesh(new THREE.PlaneGeometry(window.r3d.floorWidth, window.r3d.floorLength), new THREE.MeshPhongMaterial({ambient: 0x2d2d2d, color:0x2d2d2d, specular: 0x101010}));
   floor.rotation.x = -Math.PI/2;
   floor.position.y = -1;
   window.r3d.scene.add(floor);
   
   floor.receiveShadow = true;

   //add controls for the scene
   window.r3d.controls = new THREE.OrbitControls(window.r3d.camera, window.r3d.renderer.domElement);
};

/**
 * This method is responsible for initializing lighting in the scene
 * 
 * @returns {undefined}
 */
Repository3D.prototype.loadLighting = function() {
   //console.log("loading lights called");
   /*var ambientLight = new THREE.AmbientLight(0x777777);
   window.r3d.scene.add(ambientLight);*/
   
   window.r3d.addShadowedLight( 1, 1, 1, 0xffffff, 1.35);
   window.r3d.addShadowedLight( 0.5, 1, -1, 0xe9f3ce, 0.7);
};


/**
 * This method is supposed to upate the scene's controls.
 * Still don't know why it's behaving the way it is
 * 
 * @param {type} noRotate  set to true if you want to disable rotation for the camera
 * 
 * @returns {undefined}
 * 
 */
Repository3D.prototype.updateControls = function(noRotate) {
   //console.log(window.r3d.tmp['lookAt']);
   window.r3d.controls.center.x = window.r3d.tmp['lookAt'].x;
   window.r3d.controls.center.y = window.r3d.tmp['lookAt'].y;
   window.r3d.controls.center.z = window.r3d.tmp['lookAt'].z;  
   window.r3d.controls.noRotate = noRotate;
   if(noRotate == false){
      window.r3d.controls.rotateLeft(Math.PI);
      window.r3d.controls.rotateUp(Math.PI);
      //window.r3d.controls.reset();
   }

   //console.log(new THREE.Vector3(window.r3d.tmp['lookAt'].x, window.r3d.tmp['lookAt'].y, window.r3d.tmp['lookAt'].z));
   //console.log(window.r3d.controls);
}

/**
 * This method is responsible for adding fancy directional lighting to the scene.
 * This method is a helper method for loadLighting method
 * 
 * @param {type} x
 * @param {type} y
 * @param {type} z
 * @param {type} color
 * @param {type} intensity
 * @returns {undefined}\
 */
Repository3D.prototype.addShadowedLight = function(x, y, z, color, intensity){
   var directionalLight = new THREE.DirectionalLight( color, intensity );
   directionalLight.position.set( x, y, z );
   window.r3d.scene.add( directionalLight );

   directionalLight.castShadow = true;
   // directionalLight.shadowCameraVisible = true;

   var d = 1;
   directionalLight.shadowCameraLeft = -d;
   directionalLight.shadowCameraRight = d;
   directionalLight.shadowCameraTop = d;
   directionalLight.shadowCameraBottom = -d;

   directionalLight.shadowCameraNear = 1;
   directionalLight.shadowCameraFar = 4;

   directionalLight.shadowMapWidth = 1024;
   directionalLight.shadowMapHeight = 1024;

   directionalLight.shadowBias = -0.005;
   directionalLight.shadowDarkness = 0.15;
};

/**
 * This method is responsible for all the animations in the scene.
 * Be careful, the method is called several times a second.
 * The method is also recursive
 * 
 * @returns {undefined}
 */
Repository3D.prototype.animate = function(){
   //requestAnimationFrame delegates redraws to the browser
   requestAnimationFrame(window.r3d.animate);

   //render the scene
   if(window.r3d.isSet(window.r3d.tmp['lookAt']) && window.r3d.tmp['lookAt'] !== null) {
      window.r3d.camera.lookAt(window.r3d.tmp['lookAt']);
   }
   
   window.r3d.renderer.render(window.r3d.scene, window.r3d.camera);
   window.r3d.controls.update();
   TWEEN.update();
};

/**
 * This method is responsible for rotating objects in the scene
 * 
 * @param {type} object    The THREE.mesh to be rotated
 * @param {type} axis      The axis on which the object will be rotated
 * @param {type} degrees   The number of degrees to rotate the object
 * 
 * @returns {undefined}
 */
Repository3D.prototype.rotate = function(object, axis, degrees) {
   //console.log("rotate called");
   var radians = (Math.PI/180) * degrees;
   
   rotWorldMatrix = new THREE.Matrix4();
   rotWorldMatrix.makeRotationAxis(axis.normalize(), radians);

   // old code for Three.JS pre r54:
   //  rotWorldMatrix.multiply(object.matrix);
   // new code for Three.JS r55+:
   rotWorldMatrix.multiply(object.matrix);                // pre-multiply

   object.matrix = rotWorldMatrix;

   // old code for Three.js pre r49:
   // object.rotation.getRotationFromMatrix(object.matrix, object.scale);
   // old code for Three.js pre r59:
   // object.rotation.setEulerFromRotationMatrix(object.matrix);
   // code for r59+:
   object.rotation.setFromRotationMatrix(object.matrix);
};

/**
 * This method handles all click events in the scene.
 * Note that this does not include things like the search box, search results etc
 * 
 * @param {type} event  Data on the event. This is provided by jQuery to this method
 * 
 * @returns {undefined}
 */
Repository3D.prototype.onDocumentMouseDown = function(event) {
   //console.log("onDocumentMouseDown called");
   jQuery(window.r3d.searchBox).blur();
   
   event.preventDefault();
   
   var vector = new THREE.Vector3(( event.clientX / window.innerWidth ) * 2 - 1, - ( event.clientY / window.innerHeight ) * 2 + 1, 0.5 );//assuming the renderer covers the entire window
   window.r3d.projector.unprojectVector(vector, window.r3d.camera);
   
   var raycaster = new THREE.Raycaster( window.r3d.camera.position, vector.sub( window.r3d.camera.position ).normalize() );//create a virtual line with which to select objects intersected by it (and hence the click)
   
   
   //check if already zoomed in to sectors
   if(typeof window.r3d.t3d != 'undefined' && window.r3d.t3d.isActive() == true){
      window.r3d.t3d.onRackClicked(raycaster);
   }
   else {
      //iterate through all the tanks and check which is intersected by ray from raycaster
      
      for(var i = 0; i < window.r3d.tanks.length; i++){
         var intersects = raycaster.intersectObject(window.r3d.tanks[i].mesh);
         if(intersects.length > 0) { 
            //console.log("Tank " + window.r3d.tanks[i].id + " clicked");

            if(window.r3d.tanks[i].sectors.length == 0){//the tank had not been clicked on the last click event therefore the tank's sector labels are not showing
               window.r3d.moveCameraToObject(window.r3d.tanks[i].mesh, true, i, 6.5);
            }
            else{
               window.r3d.onTankClicked(i, raycaster);
            }
         }
      }
   }
};

/**
 * This method handles mouse clicks on a tank.
 * This method is a helper method to onDocumentMouseDown
 * 
 * @param {type} tankIndex    The index of the tank in windwor.r3d.tankData.data
 * @param {type} raycaster    The THREE.Raycaster used to determine that the tank was clicked
 * 
 * @returns {undefined}]
 */
Repository3D.prototype.onTankClicked = function(tankIndex, raycaster){
   //console.log("onTankClicked called");
   //check if towers are already being displayed
   if(typeof window.r3d.t3d == 'undefined' || window.r3d.t3d.isActive() == false){
      if(window.r3d.tanks[tankIndex].sectors.length > 0){//the tank has sector labels shown ontop of if
         for(var i = 0; i < window.r3d.tanks[tankIndex].sectors.length; i++){
            var intersects = raycaster.intersectObject(window.r3d.tanks[tankIndex].sectors[i].mesh);
            if(intersects.length > 0){
               //console.log("Sector " + window.r3d.tanks[tankIndex].sectors[i].name + " label clicked");
               var tank3D = new Tank3D({isTank: true, tankIndex : tankIndex, sectorIndex : i});
            }
         }
      }
      else{
         //console.log("The tank has no sector labels shown on top of it.");
      }
   }
   else{
      //console.log("Routing click to tank_3d for handling");
   }
};

/**
 * This method is called whenever the browser window is resized.
 * Add code here that needs to be run whenever this happens
 * 
 * @returns {undefined}
 */
Repository3D.prototype.onDocumentResize = function() {
   //console.log("documentResize called");
   var WIDTH = window.innerWidth;
   var HEIGHT = window.innerHeight;

   window.r3d.renderer.setSize(WIDTH, HEIGHT);
   window.r3d.camera.aspect = WIDTH/HEIGHT;
   window.r3d.camera.updateProjectionMatrix();
   
   window.r3d.zoomOutButton.style.left = (WIDTH - 140) + 'px';
   window.r3d.zoomOut.style.left = (WIDTH - 80) + 'px';
   window.r3d.zoomOut.style.top = 90 + 'px';
   window.r3d.zoomIn.style.left = (WIDTH - 130) + 'px';
   window.r3d.zoomIn.style.top = 90 + 'px';
   window.r3d.loadingBox.style.left = (WIDTH / 2 - (jQuery(window.r3d.loadingBox).width() / 2));
   window.r3d.loadingBox.style.top = (HEIGHT / 2 - (jQuery(window.r3d.loadingBox).height() / 2));
   window.r3d.clearSearch.style.left = window.r3d.searchBox.style.left + jQuery(window.r3d.searchBox).width();
   window.r3d.statsBox.style.left = (WIDTH -  jQuery(window.r3d.statsBox).width() - 100) + "px";
   window.r3d.statsBox.style.top = (HEIGHT * 0.6 ) + "px";
   window.r3d.virtBox.style.left = (WIDTH -  jQuery(window.r3d.virtBox).width() - 100) + "px";
   window.r3d.virtBox.style.top = ((HEIGHT * 0.6 ) - jQuery(window.r3d.virtBox).height() - 30) + "px";
   window.r3d.addBoxMTAButton.style.left = jQuery(window.r3d.zoomOutButton).position().left - (50 + jQuery(window.r3d.addBoxMTAButton).width())+"px";
   window.r3d.mtaButton.style.left = jQuery(window.r3d.zoomOutButton).position().left - (jQuery(window.r3d.mtaButton).width() + 100 + jQuery(window.r3d.addBoxMTAButton).width())+"px";
   
   window.r3d.mtaBoxListDiv.style.left = (window.innerWidth/2 - jQuery(window.r3d.mtaBoxListDiv).width()/2) + 'px';
   window.r3d.mtaBoxListDiv.style.top = (window.innerHeight/2 - jQuery(window.r3d.mtaBoxListDiv).height()/2) + 'px';
   
   window.r3d.mtaDialog.style.left = (window.innerWidth/2 - jQuery(window.r3d.mtaDialog).width()/2)+ "px";
   window.r3d.mtaDialog.style.top = (window.innerHeight/2 - jQuery(window.r3d.mtaDialog).height()/2)+ "px";
};

/**
 * This method is used to move the camera towards an THREE.mesh
 * 
 * @param {type} mesh      The mesh that we want the camera to move to
 * @param {type} isTank    Set to true if the object is a tank
 * @param {type} tankIndex The index of the tank in window.r3d.tankData.data
 * @param {type} zoomLevel The final y coordinate of the camera in the scene
 * 
 * @returns {undefined}
 */
Repository3D.prototype.moveCameraToObject = function(mesh, isTank, tankIndex, zoomLevel) {
   var obj = mesh;
   //console.log("moveCameraToObject called");
   TWEEN.removeAll();
   
   /* initialize a tween for changinging the position of the camera*/
   var initP = {
      x : window.r3d.camera.position.x,
      y : window.r3d.camera.position.y,
      z : window.r3d.camera.position.z
   };
   var targetP = {
      x : obj.position.x,
      y : zoomLevel,
      z : obj.position.z
   };
   
   var cameraPosTween = new TWEEN.Tween(initP).to(targetP, 600);
   cameraPosTween.onUpdate(function() {
      window.r3d.camera.position.set(initP.x,initP.y,initP.z);
      //window.r3d.camera.lookAt(initLA);
   });
   cameraPosTween.onComplete(function() {
      //window.r3d.camera.lookAt(initLA);
      if(isTank) window.r3d.showSectorCubes(tankIndex);
   });
   
   cameraPosTween.start();
   
   
   var initLA = new THREE.Vector3(window.r3d.tmp['lookAt'].x, window.r3d.tmp['lookAt'].y, window.r3d.tmp['lookAt'].z);
   
   var targetLA = {
      x : obj.position.x,
      y : obj.position.y,
      z : obj.position.z
   };
   
   /* initialize a tween for changing the top of the camera*/
   
   //initLA.applyQuaternion(window.r3d.camera.quaternion);
   
   var initUp = {
      x : window.r3d.camera.up.x,
      y : window.r3d.camera.up.y,
      z : window.r3d.camera.up.z
   };
   
   var targetUp = {
      x : 0,
      y : 0,
      z : -1
   };
   
   //window.r3d.camera.up = new THREE.Vector3(0, 0, -1);//prevents un-understandable rotation of camera during lookat transition http://stackoverflow.com/questions/14271672/moving-the-camera-lookat-and-rotations-in-three-js
   var cameraUpTween = new TWEEN.Tween(initUp).to(targetUp, 600);
   cameraUpTween.onUpdate(function () {
      window.r3d.camera.up = new THREE.Vector3(initUp.x, initUp.y, initUp.z);
   });
   cameraUpTween.onComplete(function () {
      window.r3d.camera.up = new THREE.Vector3(targetUp.x, targetUp.y, targetUp.z);
      jQuery(window.r3d.zoomOutButton).html("Zoom Out");
      
      jQuery(window.r3d.zoomIn).show();
      jQuery(window.r3d.zoomOut).show();
   });
   cameraUpTween.start();

   /* initialize a tween for changin the lookat of the camera */
   var cameraLATween = new TWEEN.Tween(initLA).to(targetLA, 600);
   cameraLATween.onUpdate(function(){
      window.r3d.tmp['lookAt'] = {x : initLA.x, y : initLA.y, z : initLA.z} ;
      //window.r3d.camera.lookAt(initLA);
      //window.r3d.camera.updateProjectionMatrix(); 

   });
   cameraLATween.onComplete(function(){
      window.r3d.tmp['lookAt'] = targetLA;
   });
   cameraLATween.start();
   
   window.r3d.updateControls(true);
   //window.r3d.camera.lookAt(obj.position);
};

/**
 * This method is used for changing the y coordinate of the camera in the scene
 * and thus simulate zooming in or out
 * 
 * @param {type} rate   By how much you want the camera to move.
 *                      Set to a negative number if you want the camera to zoom in
 *                      
 * @returns {undefined}
 */
Repository3D.prototype.zoom = function(rate){
   var time = Math.abs(rate * 500);
   
   /* initialize a tween for the camera's position */
   var initP = {
      x : window.r3d.camera.position.x,
      y : window.r3d.camera.position.y,
      z : window.r3d.camera.position.z
   };
   
   var targetP = {};
   targetP = {
      x : window.r3d.camera.position.x,
      y : window.r3d.camera.position.y + rate,
      z : window.r3d.camera.position.z
   };
   
   //console.log("target camera position is ", targetP);
   
   var cameraPosTween = new TWEEN.Tween(initP).to(targetP, time);
   cameraPosTween.onUpdate(function() {
      window.r3d.camera.position.set(initP.x,initP.y,initP.z);
   });
   cameraPosTween.onComplete(function() {
      window.r3d.camera.position.set(targetP.x, targetP.y, targetP.z);
   });
   
   cameraPosTween.start();
};

/**
 * This method is used for moving the camera back to a previous position.
 * If you want the camera to reset back to it's default position call this method
 * without any arguements
 * 
 * @param {type} target    The THREE.mesh which the camera should look at once it has reset
 * @param {type} zoom      The value of the y coordinate for the camera once it has reset
 * 
 * @returns {undefined}
 */
Repository3D.prototype.resetCamera = function(target, zoom){
   //check if at tank level or sector level
   //console.log("resetCamera called");
  
   var resetToTop = false;
   if(typeof window.r3d.t3d == 'undefined' || window.r3d.t3d.isAtBoxLevel() == false){
      //console.log("not at box level");
      resetToTop = true;
   }
   
   if(typeof window.r3d.t3d != 'undefined') {
      if(typeof zoom == 'undefined' && window.r3d.t3d.sector.isTank == false){//cater for when you just want to zoom out completely from search results, instead of first closing the racks then zooming out
         //console.log("booooooooya");
         resetToTop = true;
      }
   }
   
   if(resetToTop==true){
      jQuery(window.r3d.zoomIn).hide();
      jQuery(window.r3d.zoomOut).hide();
   }
   
   
   TWEEN.removeAll();
   
   /* initialize a tween for the camera's position */
   var initP = {
      x : window.r3d.camera.position.x,
      y : window.r3d.camera.position.y,
      z : window.r3d.camera.position.z
   };
   
   var targetP = {};
   if(resetToTop){
      //console.log("camera position being set to default position");
      targetP = {
         x : window.r3d.tmp['defaultCP'].x,
         y : window.r3d.tmp['defaultCP'].y,
         z : window.r3d.tmp['defaultCP'].z
      };
   }
   else {
      targetP = {
         x : target.x,
         y : zoom,
         z : target.z
      };
   }
   
   //console.log("target camera position is ", targetP);
   
   var cameraPosTween = new TWEEN.Tween(initP).to(targetP, 600);
   cameraPosTween.onUpdate(function() {
      window.r3d.camera.position.set(initP.x,initP.y,initP.z);
   });
   cameraPosTween.onComplete(function() {
      if(resetToTop) {
         window.r3d.hideAllSectors();
         jQuery(window.r3d.zoomOutButton).html("Reset");
      }
   });
   
   cameraPosTween.start();
   
   /* init another tween for the camera's up */
   var initUp = {
      x : window.r3d.camera.up.x,
      y : window.r3d.camera.up.y,
      z : window.r3d.camera.up.z
   };
   
   var targetUp = {
      x : 0,
      y : 0,
      z : -1
   };
   
   //window.r3d.camera.up = new THREE.Vector3(0, 0, -1);//prevents un-understandable rotation of camera during lookat transition http://stackoverflow.com/questions/14271672/moving-the-camera-lookat-and-rotations-in-three-js
   var cameraUpTween = new TWEEN.Tween(initUp).to(targetUp, 600);
   cameraUpTween.onUpdate(function () {
      window.r3d.camera.up = new THREE.Vector3(initUp.x, initUp.y, initUp.z);
   });
   cameraUpTween.onComplete(function () {
      window.r3d.camera.up = new THREE.Vector3(targetUp.x, targetUp.y, targetUp.z);
   });
   cameraUpTween.start();
   
   /* init another tween for the camera's lookat */
   
   var initLA = new THREE.Vector3(window.r3d.tmp['lookAt'].x, window.r3d.tmp['lookAt'].y, window.r3d.tmp['lookAt'].z);
   
   /*initLA.applyQuaternion(window.r3d.camera.quaternion);
   window.r3d.camera.up = new THREE.Vector3(0,1,0);*/
   
   var targetLA = {};
   
   if(resetToTop){
      targetLA = {
         x : window.r3d.tmp['defaultCLA'].x,
         y : window.r3d.tmp['defaultCLA'].y,
         z : window.r3d.tmp['defaultCLA'].z
      };
   }
   else {
      targetLA = {
         x : target.x,
         y : target.y,
         z : target.z
      };
   }
   
   var cameraLATween = new TWEEN.Tween(initLA).to(targetLA, 600);
   cameraLATween.onUpdate(function(){
      window.r3d.tmp['lookAt'] = {x : initLA.x, y : initLA.y, z : initLA.z} ;
   });
   cameraLATween.onComplete(function(){
      window.r3d.tmp['lookAt'] = targetLA;
   });
   cameraLATween.start();
   
   window.r3d.updateControls(!resetToTop);
};

/**
 * This method is used to determin if an object has been set
 * Note that a variable is considered set if it is null
 * 
 * @param {type} variable  The variable you want do determine if is set
 * 
 * @returns {Boolean}      TRUE if the variable is set
 */
Repository3D.prototype.isSet = function(variable){
   if(typeof variable === 'undefined') return false;
   else return true;
};

/**
 * This method is used for adding labels to the scene.
 * This method is not used at all as of now.
 * 
 * @param {type} text               The text that should go on the label
 * @param {type} x                  The x coordinate of the label in the scene
 * @param {type} y                  The y coordinate of the label in the scene
 * @param {type} z                  The z coordinate of the label in the scene
 * @param {type} size               The size of the label
 * @param {type} color              The color of the text
 * @param {type} backGroundColor    The color of the plate where the text is drawn
 * @param {type} backgroundMargin   The margins between the text and the edge of the label
 * 
 * @returns {Repository3D.prototype.createLabel.mesh|THREE.Mesh}
 */
Repository3D.prototype.createLabel = function(text, x, y, z, size, color, backGroundColor, backgroundMargin) {
   if(!backgroundMargin)
      backgroundMargin = 50;

   var canvas = document.createElement("canvas");

   var context = canvas.getContext("2d");
   context.font = size + "pt Roboto";

   var textWidth = context.measureText(text).width;
   canvas.width = textWidth + backgroundMargin;
   canvas.height = size + backgroundMargin;
   context = canvas.getContext("2d");
   context.font = size + "pt Roboto";

   if(backGroundColor) {
      context.fillStyle = backGroundColor;
      context.fillRect(canvas.width / 2 - textWidth / 2 - backgroundMargin / 2, canvas.height / 2 - size / 2 - +backgroundMargin / 2, textWidth + backgroundMargin, size + backgroundMargin);
   }

   context.textAlign = "center";
   context.textBaseline = "middle";
   context.fillStyle = color;
   context.fillText(text, canvas.width / 2, canvas.height / 2);

   // context.strokeStyle = "black";
   // context.strokeRect(0, 0, canvas.width, canvas.height);

   var texture = new THREE.Texture(canvas);
   texture.needsUpdate = true;

   var material = new THREE.MeshBasicMaterial({
      map : texture
   });

   var mesh = new THREE.Mesh(new THREE.PlaneGeometry(canvas.width, canvas.height), material);
   // mesh.overdraw = true;
   mesh.doubleSided = true;
   mesh.position.x = x - canvas.width;
   mesh.position.y = y - canvas.height;
   mesh.position.z = z;

   return mesh;
};

/**
 * This method is used to display labels for sectors in tanks on the scene once the user zooms into a tank
 * 
 * @param {type} tankIndex    The index of the tank in window.r3d.tankData.data
 * 
 * @returns {undefined}
 */
Repository3D.prototype.showSectorCubes = function(tankIndex){   
   window.r3d.hideAllSectors();
   var sectors = new Array();
   
   //check the number of sectors available in the current tank
   var tankData = window.r3d.getTankData(tankIndex);
   
   var dimens = new Array();
   dimens.push({x: -0.4, z: 0, color: 'red'});
   dimens.push({x: -0.25, z: 0.5, color: 'orange'});
   dimens.push({x: 0.25, z: 0.5, color: 'green'});
   dimens.push({x: 0.4, z: 0, color: 'blue'});
   dimens.push({x: 0.25, z: -0.5, color: 'purple'});
   dimens.push({x: -0.25, z: -0.5, color: 'pink'});
   
   for(var sectorIndex = 0; sectorIndex < dimens.length; sectorIndex++) {
      var color = dimens[sectorIndex].color;
      var name = "x";
      if(tankData == null || sectorIndex >= tankData.sectors.length){
         color = 'grey';
         //console.log("no data for sector "+sectorIndex);
      }
      else{
         name = tankData.sectors[sectorIndex].facility.slice(-1);
         if(tankData.sectors[sectorIndex].racks.length == 0) color = 'grey';
      }
      
      
      var sectorMesh = window.r3d.createSectorLabel(window.r3d.tanks[tankIndex].mesh, dimens[sectorIndex].x, dimens[sectorIndex].z, name, color);
      window.r3d.scene.add(sectorMesh);
      sectors.push({mesh : sectorMesh , dataIndex : sectorIndex});
   }
   
   window.r3d.tanks[tankIndex].sectors = sectors;
};

/**
 * This method is used to hide sector labels
 * 
 * @returns {undefined}
 */
Repository3D.prototype.hideAllSectors = function(){
   for(var tankIndex = 0; tankIndex < window.r3d.tanks.length; tankIndex++){
      for(var sectorIndex = 0; sectorIndex < window.r3d.tanks[tankIndex].sectors.length; sectorIndex++){
         window.r3d.scene.remove(window.r3d.tanks[tankIndex].sectors[sectorIndex].mesh);
      }
      window.r3d.tanks[tankIndex].sectors = new Array();
   }
   
   //hide tank3d
   if(typeof window.r3d.t3d !== 'undefined' && window.r3d.t3d.sector.isTank){
      window.r3d.t3d.clear();
   }
};

/**
 * This method is used to create a single label for a tank sector.
 * Ths method ordinarily is called by showSectorCubes
 * @param {type} tankMesh  The THREE.mesh corresponding to the tank
 * @param {type} xDiff     The x offset of the label from the centre of the tank
 * @param {type} zDiff     The z offset of the label from the centre of the tank
 * @param {type} name      The text to be put on the label
 * @param {type} color     The color of the label
 * 
 * @returns {Repository3D.prototype.createSectorLabel.mesh|THREE.Mesh}
 */
Repository3D.prototype.createSectorLabel = function(tankMesh, xDiff, zDiff, name, color) {
   var texture	= new THREEx.DynamicTexture(512,512);
	texture.context.font	= "bolder 350px Roboto";
   texture.clear(color);
   texture.drawText(name, undefined, 380, 'white');
   //var geometry = new THREE.CylinderGeometry(0.2, 0.2, 0.01, 50, 50, false);//(0.4, 0.01, 0.4);
   var geometry = new THREE.CircleGeometry(0.2, 64);
   //geometry.vertices.shift();
   var material = new THREE.MeshBasicMaterial({
       map : texture.texture,
       color : 0xd3d3d3
   });
   var mesh    = new THREE.Mesh( geometry, material );
   mesh.position.x = tankMesh.position.x + xDiff;
   mesh.position.y = tankMesh.position.y + 1;
   mesh.position.z = tankMesh.position.z + zDiff;
   mesh.rotation.x = -Math.PI/2;
   mesh.overdraw = true;
   
   return mesh;
};

/**
 * This method is responsible for adding a tank to the scene
 * 
 * @param {type} id     The id of the tank. Should correspond to the name of the tank eg 1 for tank 1
 * @param {type} x      The x coordinate of the tank in the scene
 * @param {type} z      The z coordinate of the tank in the scene
 * 
 * @returns {undefined}
 */
Repository3D.prototype.createTank = function(id, x, z){
   var labelTexture = new THREEx.DynamicTexture(512, 512);
   labelTexture.context.font	= "bolder 350px Roboto";
   labelTexture.clear('black');
   labelTexture.drawText(id, undefined, 380, 'white');
   var labelGeometry = new THREE.CircleGeometry(0.5, 64);
   var labelMaterial = new THREE.MeshBasicMaterial({
      map : labelTexture.texture,
      color : 0xd3d3d3
   });
   var labelMesh = new THREE.Mesh(labelGeometry, labelMaterial);
   labelMesh.overdraw = true;
   
   var tankLoader = new THREE.JSONLoader();
   tankLoader.load('js/models/liquid_nitrogen_tank.json', function(geometry) {
      var material = new THREE.MeshLambertMaterial({color: 0xd3d3d3});
      mesh = new THREE.Mesh(geometry, material);
      mesh.position.x = x;
      mesh.position.z = z;
      
      labelMesh.position.x = mesh.position.x;
      labelMesh.position.y = mesh.position.y;
      labelMesh.position.z = mesh.position.z + 1.1;
      
      window.r3d.tanks.push({mesh:mesh, id:id, sectors : [], label : labelMesh});
      
      window.r3d.scene.add(mesh);
      window.r3d.scene.add(labelMesh);
   });
};

/**
 * This method fetches all the data on the tanks from the server.
 * Be carefull, some code in this method runs asynchronously from code in the main thread
 * 
 * @returns {undefined}
 */
Repository3D.prototype.getAllTankData = function(){
   var uri = window.r3d.serverURI + "get_tank_details";
   jQuery.ajax({
      url: uri,
      type: 'POST',
      async: true
   }).done(function(data){
      //console.log("data gotten from server");
      window.r3d.tankData = jQuery.parseJSON(data);
      for(var index = 0; index < window.r3d.tankData.data.length; index++){
         if(window.r3d.tankData.data[index].name === "Liquid Nitrogen Tank5"){
            window.r3d.tankData.data.splice(index, 1);
            break;
         }
      }
      jQuery(window.r3d.loadingBox).hide();
   });
   jQuery(window.r3d.loadingBox).hide();
   window.r3d.tankData = {error : true};
};

/**
 * This method gets data corresponding to a tank in the window.tanks array
 * 
 * @param {type} tankIndex    The index of the tank in window.tanks
 * 
 * @returns {Window.tankData.data|window.tankData.data|window.r3d.tankData.data|Window.r3d.tankData.data}
 */
Repository3D.prototype.getTankData = function(tankIndex) {
   var tankName = "Liquid Nitrogen Tank" + window.r3d.tanks[tankIndex].id;
   var tankData = null;
   for(var i = 0; i < window.r3d.tankData.data.length; i++){
      if(window.r3d.tankData.data[i].name === tankName){
         return window.r3d.tankData.data[i];
      }
   }
   
   return null;
};

/**
 * This method is called whenever the value of the search box is changed by the user
 * 
 * @returns {undefined}
 */
Repository3D.prototype.onSearchChange = function(){
   //console.log("on search change called");
   /*
    * if number of characters has increased and there is some results cached in tmp, search the cache
    * Otherwise only search the main tank data object if the difference in characters between the last search query and current query is 3
    * */
   
   //zoom out completely before displaying search results to user
   if(typeof window.r3d.t3d != 'undefined'){
   //window.r3d.t3d.clear();
      while(window.r3d.t3d.isActive() == true){
         window.r3d.handleZoomButtonEvent();
      }
   }
   if(window.r3d.isZoomedOutCompletely() == false){
      window.r3d.handleZoomButtonEvent();
   }
      
   var currentQuery = jQuery(window.r3d.searchBox).val();
   var lastSearch = window.r3d.tmp['search'];
   
   if(currentQuery.length > 0){
      jQuery(window.r3d.clearSearch).show();
      var queryDiff = "";
      if(currentQuery.length > lastSearch.query.length){
         queryDiff = currentQuery.replace(lastSearch.query, "");
      }
      else {
         queryDiff = lastSearch.query.replace(currentQuery, "");
      }

      if(window.r3d.tmp['search'].results.length > 0 && currentQuery.length > lastSearch.query.length){
         window.r3d.search();
      }
      else if(queryDiff.length > 2){
         window.r3d.search();
      }
      else {
         //console.log("The search term entered is not worth being searched for. Skipping");
         jQuery(window.r3d.searchCanvas).empty();
      }
   }
   else {
      jQuery(window.r3d.searchCanvas).empty();
      jQuery(window.r3d.clearSearch).hide();
      window.r3d.resetMTABoxes();
   }
};

/**
 * This method searches the query provided by the user in the window.r3d.tankData data object
 * 
 * @returns {undefined}
 */
Repository3D.prototype.search = function() {
   var query = jQuery(window.r3d.searchBox).val();
   var lastSearch = window.r3d.tmp['search'];
   var tankScore = 1;
   var sectorScore = 2;
   var rackScore = 3;
   var boxScore = 4;
   var projectScore = 3;
   var organismScore = 3;
   
   var results = new Array();
   
   if(lastSearch.results.length == 0){
      //console.log("Searching the entire tank data object");
      var tankData = window.r3d.tankData.data;
      if(tankData != null){
         for(var tankIndex = 0; tankIndex < tankData.length; tankIndex++){
            var ts = window.r3d.fuzzySearch(tankData[tankIndex].name, query, tankScore);
            if(ts > 0){
               results.push({type:"tank", match:tankData[tankIndex].name, score:ts, index:[tankIndex]});
            }
            
            var sectors = tankData[tankIndex].sectors;
            for(var sectorIndex = 0; sectorIndex < sectors.length; sectorIndex++){
               var ss = window.r3d.fuzzySearch(sectors[sectorIndex].facility, query, sectorScore);
               if(ss > 0){
                  results.push({type:"sector", match:sectors[sectorIndex].facility, score:ss, index:[tankIndex, sectorIndex]});
               }
               
               var racks = sectors[sectorIndex].racks;
               for(var rackIndex = 0; rackIndex < racks.length; rackIndex++){
                  /*var rs = window.r3d.fuzzySearch(racks[rackIndex].name, query, rackScore);
                  if(rs > 0){
                     results.push({type:"rack", match:racks[rackIndex].name, score:rs, index:[tankIndex, sectorIndex, rackIndex]});
                  }*/
                  
                  var boxes = racks[rackIndex].boxes;
                  for(var boxIndex = 0; boxIndex < boxes.length; boxIndex++){
                     var bs = window.r3d.fuzzySearch(boxes[boxIndex].box_name, query, boxScore);
                     
                     for(var pIndex = 0; pIndex < boxes[boxIndex].sample_projects.length; pIndex++){
                        bs = bs + window.r3d.fuzzySearch(boxes[boxIndex].sample_projects[pIndex], query, projectScore);
                     }
                     
                     for(var oIndex = 0; oIndex < boxes[boxIndex].sample_organisms.length; oIndex++){
                        bs = bs + window.r3d.fuzzySearch(boxes[boxIndex].sample_organisms[oIndex], query, organismScore);
                     }
                     
                     for(var stIndex = 0; stIndex < boxes[boxIndex].sample_types.length; stIndex++){
                        bs = bs + window.r3d.fuzzySearch(boxes[boxIndex].sample_types[stIndex], query, organismScore);
                     }
                     
                     if(bs > 0){
                        results.push({type:"box", match:boxes[boxIndex].box_name, score:bs, index:[tankIndex, sectorIndex, rackIndex, boxIndex]});
                     }
                  }
               }
            }
         }
      }
   } 
   else {
      
   }
   
   window.r3d.showSearchResults(results);
};

/**
 * This method displays search results to the user.
 * It however first calls a method that groups the search results
 * 
 * @param {type} results   The ungrouped search results
 * 
 * @returns {undefined}
 */
Repository3D.prototype.showSearchResults = function(results) {
   //console.log("showSearchResults called");
   
   var groups = window.r3d.groupSearchResults(results);
   
   window.r3d.updateMTABoxes(groups);
   //console.log(groups);
   
   jQuery(window.r3d.searchCanvas).empty();
   jQuery(window.r3d.searchCanvas).show();
   
   //go through all the groups and get their respective cards
   for(var groupIndex = 0; groupIndex < groups.length; groupIndex++){
      var color = window.r3d.randomColor((groupIndex + 1) * 17);//spread out the colors
      groups[groupIndex].color = color;
      //console.log(color);
      
      var colorDiv = jQuery("<div><div>");
      ////console.log({background:color, position: "absolute", left: "90%", top:"0%", width:"10%", height:"100%"});
      colorDiv.css({background: color, position: "absolute", left: "90%", top:"0%", width:"10%", height:"100%"});
      
      //console.log(colorDiv[0].outerHTML);
      var currGroupHTML = "";
      if(groups[groupIndex].type == "box"){
         if(groups[groupIndex].extra.length == 1){//only one box in this group
            currGroupHTML =  window.r3d.getBoxCard(groups[groupIndex],colorDiv[0].outerHTML);
         }
         else{
            currGroupHTML = window.r3d.getBoxGroupCard(groups[groupIndex], colorDiv[0].outerHTML);
         }
      }
      else if(groups[groupIndex].type == "sector"){
         if(groups[groupIndex].extra.length == 1){
            currGroupHTML = window.r3d.getSectorCard(groups[groupIndex], colorDiv[0].outerHTML);
         }
         else {
            currGroupHTML = window.r3d.getSectorGroupCard(groups[groupIndex], colorDiv[0].outerHTML);
         }
      }
      else if(groups[groupIndex].type == "tank"){
         if(groups[groupIndex].extra.length == 1){
            currGroupHTML = window.r3d.getTankCard(groups[groupIndex], colorDiv[0].outerHTML);
         }
         else {
            currGroupHTML = window.r3d.getTankGroupCard(groups[groupIndex], colorDiv[0].outerHTML);
         }
      }
      
      var currGroupJQObject = jQuery(currGroupHTML);
      var currGroupID = 'search_group_'+groupIndex;
      currGroupJQObject.attr('id', currGroupID);
      
      //add a click listener for the current group
      currGroupJQObject.on('click',null,{groups : groups, groupIndex: groupIndex}, function(e){
         /*if search results have already been visualized
            - toggle boxes that are not from the current group to be invisible if they are visible
            - otherwise toggle all boxes to be visible
         */
        
         if(typeof window.r3d.t3d != 'undefined' && window.r3d.t3d.isActive() == true){
            var groups = e.data.groups;
            var groupIndex = e.data.groupIndex;
            
            if(groups.length > 1){//toggling in between groups is only useful when you have more than one group
               
               if(window.r3d.t3d.getNoVisibleBoxes() == groups[groupIndex].extra.length){//already only showing boxes from this group. Show all the boxes
                  
                  while(window.r3d.t3d.isActive()){
                     window.r3d.t3d.clear();
                  }
                  
                  //set MTA boxes to all the searchec boxes
                  window.r3d.updateMTABoxes(groups);
                  
                  //render all the searched boxes
                  var tank3D = new Tank3D({isTank: false, data:groups});
               }
               else {//hide all other boxes
                  while(window.r3d.t3d.isActive()){
                     window.r3d.t3d.clear();
                  }
                  
                  //set MTA boxes to only the ones in the clicked card
                  window.r3d.updateMTABoxes([groups[groupIndex]]);
                  
                  //render boxes from the clicked group/card
                  var tank3D = new Tank3D({isTank: false, data:[groups[groupIndex]]});
               }
           }
         }
      });
      
      jQuery(window.r3d.searchCanvas).append(currGroupJQObject);
      
      ////console.log("current group jquery object", currGroupJQObject);
   }
   
   
   //add a click listener to the search canvas (where the search results are)
   jQuery(window.r3d.searchCanvas).unbind('click').click(function (){
      //called whenever a search result card is clicked
      
      //display the racks corresponding to the search result
      if(typeof  window.r3d.t3d == 'undefined' || window.r3d.t3d.isActive() == false){
         var tank3D = new Tank3D({isTank: false, data: groups});
      }
   });
};

/**
 * This method creates a card corresponding to a group of search results that contains only
 * one box
 * 
 * @param {type} data      The data corresponding to the search results group
 * @param {type} colorDiv  The color assigned to the results group
 * 
 * @returns {String}       The html code corresponding to the generated card
 */
Repository3D.prototype.getBoxCard = function(data, colorDiv) {
   var boxData = window.r3d.tankData.data[data.extra[0].index[0]].sectors[data.extra[0].index[1]].racks[data.extra[0].index[2]].boxes[data.extra[0].index[3]];
   
   var html = "<div class='card-3d'>";
   html = html + "<h1>"+data.extra[0].match+"</h1>";
   html = html + "<h2><strong>Postion: </strong>"+window.r3d.getPositionText(data.extra[0].index)+"<h2>";
   html = html +  "<h2><strong>Status: </strong>"+boxData.status+"</h2>";
   html = html +  "<h2><strong>Owner: </strong>"+window.r3d.getKeeperName(boxData.keeper)+"</h2>";
   html = html +  "<h2><strong>Date Added: </strong>"+boxData.date_added+"</h2>";
   html = html + colorDiv;
   html = html + "</div>";
   
   return html;
};

/**
 * This method creates a card corresponding to a group of search results that contains more than
 * one box
 * 
 * @param {type} data      The data corresponding to the search results group
 * @param {type} colorDiv  The color assigned to the results group
 * 
 * @returns {String}       The html code corresponding to the generated card
 */
Repository3D.prototype.getBoxGroupCard = function(data, colorDiv){
   var html = "<div class='card-3d'>";
   
   var names = "";
   for(var index = 0; index < data.extra.length; index++){
      if(names.length < 30){
         if(names.length === 0) names = data.extra[index].match;
         else names = names + ", " + data.extra[index].match;
      }
      else{
         names = names + " ...";
         break;
      }
   }
   
   var status = new Array();
   var keepers = new Array();
   for(var index = 0; index < data.extra.length; index++){
      var boxData = window.r3d.tankData.data[data.extra[index].index[0]].sectors[data.extra[index].index[1]].racks[data.extra[index].index[2]].boxes[data.extra[index].index[3]];
      
      ////console.log("current box's status = "+boxData.status);
      ////console.log("current box's keeper = "+boxData.keeper);
      if(jQuery.inArray(boxData.status, status) == -1){
         status.push(boxData.status);
      }
      if(boxData.keeper != null && boxData.keeper != "null" && jQuery.inArray(boxData.keeper, keepers) == -1){
         keepers.push(boxData.keeper);
      }
   }
   
   for(var index = 0; index < keepers.length; index++){
      keepers[index] = window.r3d.getKeeperName(keepers[index]);
   }
   var keeperLabel = "Owner";
   if(keepers.length > 1){
      keeperLabel = "Owners";
   }
   
   html = html + "<h1>"+data.extra.length+" Boxes</h1>";
   html = html + "<h2><strong>Range: </strong>"+names+"<h2>";
   html = html + "<h2><strong>status: </strong>"+status.join(", ")+"</h2>";
   html = html + "<h2><strong>" + keeperLabel + ": </strong>"+keepers.join(", ")+"</h2>";
   html = html + colorDiv;
   html = html + "</div>";
   
   return html;
};

/**
 * This method creates a card corresponding to a group of search results that contains only
 * one tank
 * 
 * @param {type} data      The data corresponding to the search results group
 * @param {type} colorDiv  The color assigned to the results group
 * 
 * @returns {String}       The html code corresponding to the generated card
 */
Repository3D.prototype.getTankCard = function(data, colorDiv){
   //console.log("getTankCard called");
   var tankData = window.r3d.tankData.data[data.extra[0].index[0]];
   //console.log(tankData);
   
   var noBoxes = 0;
   var keepers = new Array();
   for(var sectorIndex = 0; sectorIndex < tankData.sectors.length; sectorIndex++) {
      for(var rackIndex = 0; rackIndex < tankData.sectors[sectorIndex].racks.length; rackIndex++){
         noBoxes = noBoxes + tankData.sectors[sectorIndex].racks[rackIndex].boxes.length;
         for(var boxIndex = 0; boxIndex < tankData.sectors[sectorIndex].racks[rackIndex].boxes.length; boxIndex++){
            if(jQuery.inArray(tankData.sectors[sectorIndex].racks[rackIndex].boxes[boxIndex].keeper, keepers) == -1){
               keepers.push(tankData.sectors[sectorIndex].racks[rackIndex].boxes[boxIndex].keeper);
            }
         }
      }
   }
   
   var keeperLabel = "Box Owner";
   if(keepers.length > 1){
      keeperLabel = "Box Owners";
   }
   for(var index = 0; index < keepers.length; index++){
      keepers[index] = window.r3d.getKeeperName(keepers[index]);
   }
   
   var html = "<div class='card-3d'>";
   html = html + "<h1>"+data.extra[0].match+"</h1>";
   html = html + "<h2><strong>Number of boxes: </strong>"+noBoxes+"<h2>";
   html = html +  "<h2><strong>" + keeperLabel + ": </strong>" + keepers.join(", ") + "</h2>";
   html = html + colorDiv;
   html = html + "</div>";
   
   return html;
};

/**
 * This method creates a card corresponding to a group of search results that contains more than
 * one tank
 * 
 * @param {type} data      The data corresponding to the search results group
 * @param {type} colorDiv  The color assigned to the results group
 * 
 * @returns {String}       The html code corresponding to the generated card
 */
Repository3D.prototype.getTankGroupCard = function(data, colorDiv){
   //console.log("getTankGroupCard called");
   var allTankData = new Array();
   for(var index = 0; index < data.extra.length; index++){
      allTankData.push(window.r3d.tankData.data[data.extra[index].index[0]]);
   }
   
   //console.log(allTankData);
   
   var noBoxes = 0;
   var keepers = new Array();
   var tankNames = new Array();
   
   for(var tankIndex = 0; tankIndex < allTankData.length; tankIndex++){
      var tankData = allTankData[tankIndex];
      tankNames.push(allTankData[tankIndex].name.replace(/Liquid Nitrogen /gi,''));
      
      for(var sectorIndex = 0; sectorIndex < tankData.sectors.length; sectorIndex++) {
         for(var rackIndex = 0; rackIndex < tankData.sectors[sectorIndex].racks.length; rackIndex++){
            noBoxes = noBoxes + tankData.sectors[sectorIndex].racks[rackIndex].boxes.length;
            for(var boxIndex = 0; boxIndex < tankData.sectors[sectorIndex].racks[rackIndex].boxes.length; boxIndex++){
               if(jQuery.inArray(tankData.sectors[sectorIndex].racks[rackIndex].boxes[boxIndex].keeper, keepers) == -1){
                  keepers.push(tankData.sectors[sectorIndex].racks[rackIndex].boxes[boxIndex].keeper);
               }
            }
         }
      }
   }
   
   var keeperLabel = "Box Owner";
   if(keepers.length > 1){
      keeperLabel = "Box Owners";
   }
   for(var index = 0; index < keepers.length; index++){
      keepers[index] = window.r3d.getKeeperName(keepers[index]);
   }
   
   var html = "<div class='card-3d'>";
   html = html + "<h1>"+allTankData.length+" Tanks</h1>";
   html = html + "<h2><strong>Range: </strong>"+tankNames.join(", ")+"<h2>";
   html = html + "<h2><strong>Number of boxes: </strong>"+noBoxes+"<h2>";
   html = html +  "<h2><strong>" + keeperLabel + ": </strong>" + keepers.join(", ") + "</h2>";
   html = html + colorDiv;
   html = html + "</div>";
   
   return html;
};

/**
 * This method creates a card corresponding to a group of search results that contains only
 * one sector
 * 
 * @param {type} data      The data corresponding to the search results group
 * @param {type} colorDiv  The color assigned to the results group
 * 
 * @returns {String}       The html code corresponding to the generated card
 */
Repository3D.prototype.getSectorCard = function(data, colorDiv) {
   var sectorData = window.r3d.tankData.data[data.extra[0].index[0]].sectors[data.extra[0].index[1]];
   
   var noBoxes = 0;
   var noRacks = 0;
   var keepers = new Array();
   
   if(typeof sectorData != 'undefined'){
      noRacks = sectorData.racks.length;
      
      for(var rackIndex = 0; rackIndex < sectorData.racks.length; rackIndex++){
         noBoxes = noBoxes + sectorData.racks[rackIndex].boxes.length;
         for(var boxIndex = 0; boxIndex < sectorData.racks[rackIndex].boxes.length; boxIndex++){
            if(jQuery.inArray(sectorData.racks[rackIndex].boxes[boxIndex].keeper, keepers) == -1){
               keepers.push(sectorData.racks[rackIndex].boxes[boxIndex].keeper);
            }
         }
      }
   }
   
   var keeperLabel = "Box Owner";
   if(keepers.length > 1){
      keeperLabel = "Box Owners";
   }
   for(var index = 0; index < keepers.length; index++){
      keepers[index] = window.r3d.getKeeperName(keepers[index]);
   }
   
   var html = "<div class='card-3d'>";
   html = html + "<h1>"+data.extra[0].match+"</h1>";
   html = html + "<h2><strong>Number of racks: </strong>"+noRacks+"<h2>";
   html = html + "<h2><strong>Number of boxes: </strong>"+noBoxes+"<h2>";
   html = html +  "<h2><strong>" + keeperLabel + ": </strong>" + keepers.join(", ") + "</h2>";
   html = html + colorDiv;
   html = html + "</div>";
   
   return html;
};

/**
 * This method creates a card corresponding to a group of search results that contains more than
 * one sector
 * 
 * @param {type} data      The data corresponding to the search results group
 * @param {type} colorDiv  The color assigned to the results group
 * 
 * @returns {String}       The html code corresponding to the generated card
 */
Repository3D.prototype.getSectorGroupCard = function(data, colorDiv) {
   
   var allSectorData = new Array();
   
   for(var index = 0; index < data.extra.length; index++){
      allSectorData.push(window.r3d.tankData.data[data.extra[index].index[0]].sectors[data.extra[index].index[1]]);
   }
   
   var noBoxes = 0;
   var noRacks = 0;
   var keepers = new Array();
   var sectorNames = "";
   
   for(var sectorIndex = 0; sectorIndex < allSectorData.length; sectorIndex++){
      var sectorData = allSectorData[sectorIndex];
      
      if(typeof sectorData != 'undefined'){
         noRacks = noRacks + sectorData.racks.length;
         
         if(sectorNames.length < 30) sectorNames = sectorNames + ", "+ allSectorData[sectorIndex].facility;
         else if (sectorIndex == allSectorData.length - 1) sectorNames = sectorNames + " ...";

         for(var rackIndex = 0; rackIndex < sectorData.racks.length; rackIndex++){
            noBoxes = noBoxes + sectorData.racks[rackIndex].boxes.length;
            for(var boxIndex = 0; boxIndex < sectorData.racks[rackIndex].boxes.length; boxIndex++){
               if(jQuery.inArray(sectorData.racks[rackIndex].boxes[boxIndex].keeper, keepers) == -1){
                  keepers.push(sectorData.racks[rackIndex].boxes[boxIndex].keeper);
               }
            }
         }
      }
   }
   
   var keeperLabel = "Box Owner";
   if(keepers.length > 1){
      keeperLabel = "Box Owners";
   }
   for(var index = 0; index < keepers.length; index++){
      keepers[index] = window.r3d.getKeeperName(keepers[index]);
   }
   
   var html = "<div class='card-3d'>";
   html = html + "<h1>"+ allSectorData.length+" Sectors</h1>";
   html = html + "<h2><strong>Range: </strong>"+sectorNames+"<h2>";
   html = html + "<h2><strong>Number of racks: </strong>"+noRacks+"<h2>";
   html = html + "<h2><strong>Number of boxes: </strong>"+noBoxes+"<h2>";
   html = html +  "<h2><strong>" + keeperLabel + ": </strong>" + keepers.join(", ") + "</h2>";
   html = html + colorDiv;
   html = html + "</div>";
   
   return html;
};

/**
 * This method constructs the position of a rack/sector/box based on the hierarchical
 * parents of the 'object'
 * 
 * @param {type} positionIndexes    The indexes of the hierarchical parents of the object
 *                                  in their respective arrays e.g for tanks window.tankData.data array
 *                                  
 * @returns {String}                A string represeting the position of the object in it's heirarchical parents
 */
Repository3D.prototype.getPositionText = function(positionIndexes){
   var positionText = "";
   var tankData = window.r3d.tankData.data;
   var levelData = tankData;
   for(var i = 0; i < positionIndexes.length; i++){
      if(i == 0){//tank level
         positionText = levelData[positionIndexes[0]].name.replace(/Liquid\sNitrogen\s/gi, "");
         levelData = levelData[positionIndexes[0]].sectors;
      }
      else if(i == 1){//sector level
         positionText = positionText + " > " + levelData[positionIndexes[1]].facility.replace(/Tank[0-9]:/gi,"");
         levelData =  levelData[positionIndexes[1]].racks;
      }
      
      else if(i == 2){//rack level
         positionText = positionText + " > " + levelData[positionIndexes[2]].name;
         levelData = levelData[positionIndexes[2]].boxes;
      }
      
      else if(i == 3){//box level
         positionText = positionText + " > Position " + levelData[positionIndexes[3]].rack_position;
      }
   }
   
   return positionText;
}

/**
 * This method searches for a query in the string
 * 
 * @param {type} string    The string on which to search the query
 * @param {type} query     The query to be searched
 * @param {type} score     The maximum score to be returned if query perfectly matches the string
 * 
 * @returns {Number}       The fuzzy score based on the similarity of the query to the string.
 *                         Will be 0 if at least one character of the query is not in the string
 */
Repository3D.prototype.fuzzySearch = function(string, query, score){
   query = query.replace(" ", "");
   var qRegex = query.toLowerCase().split("").reduce(function(a,b){ return a+".*"+b; });
   var result = (new RegExp(qRegex)).test(string.toLowerCase());
   if(result == true){
      return (query.length/string.length) * score;
   }
   else {
      return 0;
   }
};

/**
 * This method groups search results based on 
 *    - the type of search result (e.g box/tank etc)
 *    - the fuzzy search score of the search results (for results that are not boxes)
 *    - the owner of the box (for results that are boxes)
 */
Repository3D.prototype.groupSearchResults = function(results){
   var groups = new Array();
   for(var resultIndex = 0; resultIndex < results.length; resultIndex++){
      //search if group exists for current result
      if(groups.length > 0){
         //search for group
         var groupFound = false;
         if(results[resultIndex].type != 'box'){//grouping of results that are not boxes different from boxes
            for(var groupIndex = 0; groupIndex < groups.length; groupIndex++){
               if(groups[groupIndex].type == results[resultIndex].type && groups[groupIndex].score == results[resultIndex].score){
                  groups[groupIndex].extra.push({index:results[resultIndex].index, match: results[resultIndex].match});
                  groupFound = true;
                  break;
               }
            }
         }
         else {//grouping of boxes based on project sampling trips, organisms, related samples
            ////console.log("grouping as box");
            if(results[resultIndex].score < 4){//only group boxes that don't have a perfect score
               //get project(keeper), sampling trip organisms and parent sample for all (aliquots)
               var boxData = window.r3d.tankData.data[results[resultIndex].index[0]].sectors[results[resultIndex].index[1]].racks[results[resultIndex].index[2]].boxes[results[resultIndex].index[3]];
               var keeper = boxData.keeper;
               
               for(var groupIndex = 0; groupIndex<groups.length; groupIndex++){
                  if(groups[groupIndex].type == results[resultIndex].type && groups[groupIndex].keeper == keeper){
                     ////console.log("group already exists for "+keeper+"group size is "+groups.length);
                     if(groups[groupIndex].score < 4){//to avoid grouping non-perfect score boxes with those that have a perfect score
                        groups[groupIndex].score = (groups[groupIndex].score +  results[resultIndex].score)/2;// ;)
                        groups[groupIndex].extra.push({index:results[resultIndex].index, match: results[resultIndex].match});
                        groupFound = true;
                        break;
                     }
                  }
               }
            }
         }
         if(groupFound == false){
            var resultInserted = false;
            for(var groupIndex = 0; groupIndex < groups.length; groupIndex++){
               if(groups[groupIndex].score < results[resultIndex].score){
                  if(results[resultIndex].type != "box"){
                     groups.splice(groupIndex, 0, {type:results[resultIndex].type, score:results[resultIndex].score, extra:[{index:results[resultIndex].index, match:results[resultIndex].match}]});
                  }
                  else {
                     var boxData = window.r3d.tankData.data[results[resultIndex].index[0]].sectors[results[resultIndex].index[1]].racks[results[resultIndex].index[2]].boxes[results[resultIndex].index[3]];
                     //console.log("creating new group for "+boxData.keeper);
                     groups.splice(groupIndex, 0, {type:results[resultIndex].type, score:results[resultIndex].score, keeper:boxData.keeper, extra:[{index:results[resultIndex].index, match:results[resultIndex].match}]});
                     ////console.log(groups[groupIndex]);
                  }
                  resultInserted = true;
                  break;
               }
            }
            if(resultInserted == false){
               if(results[resultIndex].type != "box"){
                  groups.push({type:results[resultIndex].type, score:results[resultIndex].score, extra:[{index:results[resultIndex].index, match:results[resultIndex].match}]});
               }
               else {
                  var boxData = window.r3d.tankData.data[results[resultIndex].index[0]].sectors[results[resultIndex].index[1]].racks[results[resultIndex].index[2]].boxes[results[resultIndex].index[3]];
                  //console.log("pushing group for "+boxData.keeper+" to end of groups");
                  //console.log("first element in group = "+boxData.box_name);
                  groups.push({type:results[resultIndex].type, score:results[resultIndex].score, keeper:boxData.keeper, extra:[{index:results[resultIndex].index, match:results[resultIndex].match}]});
               }
            }
         }
      }
      else {//first element in the results. Just add it to the group
         if(results[resultIndex].type != "box"){
            groups.push({type:results[resultIndex].type, score:results[resultIndex].score, extra:[{index:results[resultIndex].index, match:results[resultIndex].match}]});
         }
         else {
            var boxData = window.r3d.tankData.data[results[resultIndex].index[0]].sectors[results[resultIndex].index[1]].racks[results[resultIndex].index[2]].boxes[results[resultIndex].index[3]];
            groups.push({type:results[resultIndex].type, score:results[resultIndex].score, keeper:boxData.keeper, extra:[{index:results[resultIndex].index, match:results[resultIndex].match}]});
         }
      }
   }
   
   return groups;
};

/**
 * This method fetches the names of box keepers from the server.
 * Note that if the names of the keepers have already been cached,
 * the cached values are what will be returned
 * 
 * @returns {window.r3dtmp.box_keepers|Window.r3dtmp.box_keepers} Names of the box keepers
 */
Repository3D.prototype.getBoxKeepers = function(){
   var uri = window.r3d.serverURI + 'get_box_keepers';
   if(typeof window.r3d.tmp['box_keepers'] == 'undefined') {
      jQuery.ajax({
         url: uri,
         type: 'POST',
         async: false
      }).done(function(data){
         window.r3d.tmp['box_keepers'] = jQuery.parseJSON(data);
         return window.r3d.tmp['box_keepers'];
      });
   }
   return window.r3d.tmp['box_keepers'];
};

/**
 * This method returns the name of the box keeper corresponding to an id
 * 
 * @param {type} id     The id of the keeper in the database
 * 
 * @returns {String}    The name of the box keeper
 */
Repository3D.prototype.getKeeperName = function (id){
   var keepers = window.r3d.getBoxKeepers();
   if(id != null && id != "null"){
      for(var index = 0; index < keepers.length; index++){
         if(keepers[index].count == id){
            return keepers[index].name;
         }
      }
   }
   
   return "";
};

/**
 * This method generates a random sequencial color given an index
 * 
 * @param {type} i            The index to be used to generate the color
 * 
 * @returns {@var;i|Number}   Hexadecimal representation of the generated color
 */
Repository3D.prototype.sequentialColor = function(i) {
   var
           r,
           g,
           b;

   i %= 216;
   if (i < 0)
   {
      i = -i;
   }
   r = Math.floor(i / 36);
   i %= 36;
   g = Math.floor(i / 6);
   b = i % 6;
   return r * 0x330000 + g * 0x003300 + b * 0x000033;
};

/**
 * This method is used to generate a random color based on an index
 * 
 * @param {type} i         The index used to generate the color
 * @returns {unresolved}   The string representation of the color eg #ffffff
 */
Repository3D.prototype.randomColor = function(i) {
   return window.r3d.colorToString(window.r3d.sequentialColor((i << 7) % 215));
};

/**
 * This method converts the hex representation of a color to a string 
 * 
 * @param {type} color  Hexadecimal representation of a color eg 0xffffff
 * 
 * @returns {String}    The string representation of the color eg #ffffff
 */
Repository3D.prototype.colorToString = function(color) {
   var
           cStr = color.toString(16);

   while (cStr.length < 6)
   {
      cStr = '0' + cStr;
   }
   return '#' + cStr;
};

/**
 * This method handles the click event for the reset button
 * @returns {undefined}
 */
Repository3D.prototype.handleZoomButtonEvent = function() {
   if(typeof window.r3d.t3d == 'undefined' || window.r3d.t3d.isActive() == false){
      window.r3d.resetCamera();
   }
   else {
      window.r3d.t3d.clear();
   }
};

/**
 * This method is used to determine if the scene is in its original state
 * 
 * @returns {Boolean}   TRUE if the scene is in its original state
 */
Repository3D.prototype.isZoomedOutCompletely = function () {
   if(window.r3d.camera.position.x == window.r3d.tmp['defaultCP'].x && window.r3d.camera.position.y == window.r3d.tmp['defaultCP'].y && window.r3d.camera.position.z == window.r3d.tmp['defaultCP'].z){
      return true;
   }
   return false;
};

/**
 * This function updates the array box IDs in window.r3d.tmp['mta_boxes']
 * 
 * @param {Array} groupedResults Object generated by groupSearchResults containing boxes to be considered in when creating the MTA
 * 
 * @returns {undefined}
 */
Repository3D.prototype.updateMTABoxes = function (groupedResults) {
   console.log("update MTA boxes called");
   window.r3d.resetMTABoxes();
   
   var boxIDs = new Array();
   for(var i = 0; i < groupedResults.length; i++){
      if(groupedResults[i].type == 'box'){
         var groupBoxes = groupedResults[i].extra;
         for(var j = 0; j < groupBoxes.length; j++){
            var boxIndex = groupBoxes[j].index;//an array of parent indexes in the window.r3d.tankData object
            var boxID = window.r3d.tankData.data[boxIndex[0]].sectors[boxIndex[1]].racks[boxIndex[2]].boxes[boxIndex[3]].box_id;
            var boxName = window.r3d.tankData.data[boxIndex[0]].sectors[boxIndex[1]].racks[boxIndex[2]].boxes[boxIndex[3]].box_name;
            window.r3d.addToMTABoxes(boxID, boxName);
         }
      }
   }
};

/**
 * This function adds a box to the tmp MTA box list
 * 
 * @param {type} boxId
 * @param {type} boxName
 * @returns {undefined}
 */
Repository3D.prototype.addToMTABoxes = function(boxId, boxName){
   //make sure the MTA box list is hidded while adding boxes
   if(jQuery(window.r3d.mtaBoxListDiv).is(":visible")){
      jQuery(window.r3d.mtaBoxListDiv).hide();
   }
   
   if(!jQuery(window.r3d.addBoxMTAButton).is(":visible")){
      console.log("showing MTA button");
      
      jQuery(window.r3d.addBoxMTAButton).show();
   }
   
   window.r3d.tmp.mta_boxes.push({id: boxId, name:boxName});
};


/**
 * This function resets the tmp MTA boxes to a blank array and does the necesary events related to the MTA array being empty
 * @returns {undefined}
 */
Repository3D.prototype.resetMTABoxes = function() {
   if(jQuery(window.r3d.mtaBoxListDiv).is(":visible")){
      jQuery(window.r3d.mtaBoxListDiv).hide();
   }
   
   console.log("reset MTA boxes called");
   
   window.r3d.tmp['mta_boxes'] = new Array();
   
   jQuery(window.r3d.mtaButton).hide();
   jQuery(window.r3d.addBoxMTAButton).hide();
   
};

/**
 * This function populates the list of boxes that can be added to the MTA
 * @returns {undefined}
 */
Repository3D.prototype.populateMTABoxList = function(){
   jQuery(window.r3d.mtaBoxList).empty();
   for(var i = 0; i < window.r3d.mtaFinalList.length; i++){
      jQuery(window.r3d.mtaBoxList).append("<li><input type='checkbox' id='"+window.r3d.mtaFinalList[i].id+"' checked='checked'/>"+window.r3d.mtaFinalList[i].name+"<br /></li>");
   }
   
   window.r3d.mtaBoxListDiv.style.left = (window.innerWidth/2 - jQuery(window.r3d.mtaBoxListDiv).width()/2) + 'px';
   window.r3d.mtaBoxListDiv.style.top = (window.innerHeight/2 - jQuery(window.r3d.mtaBoxListDiv).height()/2) + 'px';
};

/**
 * This function validates fields in an MTA request
 * @returns {Boolean} true if MTA request is valid
 */
Repository3D.prototype.validateMTARequest = function(){
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
   
   var boxIDs = new Array();
   $('#mta_box_list input:checked').each(function() {
      boxIDs.push($(this).attr('id'));
   });
   
   if(boxIDs.length == 0){
      window.alert("Please select at least one box for the MTA");
      return false;
   }
   
   return true;
};

Repository3D.prototype.processMTARequest = function(){
   if(window.r3d.validateMTARequest()){
      var boxIDs = "";
      $('#mta_box_list input:checked').each(function() {
         if(boxIDs.length > 0){
            boxIDs = boxIDs + "," + $(this).attr('id');
         }
         else {
            boxIDs = $(this).attr('id');
         }
      });
      
      $("#mta_submit_btn").attr("disabled", true);
      jQuery(window.r3d.loadingBox).show();
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
            box_ids:boxIDs
         },
         success:function(data){
            console.log(data);
            var json = jQuery.parseJSON(data);
            if(json.error == false){
               jQuery(window.r3d.mtaDialog).hide();
               jQuery(window.r3d.mtaBoxListDiv).hide();
               window.alert("Your request has been successfully submitted");
            }
            else {
               window.alert(json.error_message);
            }
         },
         error:function(){
            window.alert("An error occurred while proccessing your request");
         },
         complete: function(){
            jQuery(window.r3d.loadingBox).hide();
            console.log("complete");
            $("#mta_submit_btn").removeAttr("disabled");
         }
         
      });
   }
};
