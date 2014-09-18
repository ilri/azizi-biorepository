//when the worker get's the message, start doing work
self.addEventListener('message', function(e){
   /*
    * In an effort to eliminate looping through all the filters to determine if a sample qualifies:
    *    - generate strings corresponding to the different filters 
    *          e.g string containing ids of projects seperated by a delimiter (in this case :)
    *    - for each sample check in the corresponding filter string if the sample's value is contained in the string
    *          e.g for sample 'a' check if the substring ':' + a.Project + ':' is in projI
    *          #genius
    */
   var data = JSON.parse(e.data);//do not use jQuery because it is a DOM element which cannot be touched within a web worker
   
   self.vs = {};
   
   self.vs.data = data.data;
   self.vs.filters = data.filters;
   var checkFilterIn = data.checkFilterIn;
   
   var projI = ":" + self.vs.filters.projects.join(":") + ":";
   //console.log("project search index = ", projI);
   var orgI = ":" + self.vs.filters.organisms.join(":") + ":";
   //console.log("organism search index = ", orgI);
   var stI = ":" + self.vs.filters.sampleTypes.join(":") + ":";
   //console.log("sample type index = ",stI);
   var testI = ":" + self.vs.filters.tests.join(":") + ":";
   //console.log("test index = ", testI);
   var resI = ":" + self.vs.filters.results.join(":") + ":";
   //console.log("result index = ", resI);
   
   var histogram = {};
   
   console.log("filterIn before"+ self.vs.data.filterIn.length);
   console.log("filterOut before"+ self.vs.data.filterOut.length);
   
   if(checkFilterIn == null || checkFilterIn == true){
      console.log("checking filter in array");
      for(var index = 0; index < self.vs.data.filterIn.length; index++){
         //check if element meets all the criteria

         if(projI.indexOf(":" + self.vs.data.filterIn[index].Project + ":") == -1 ){//sample's project not part of filter
            self.vs.data.filterOut.push(self.vs.data.filterIn[index]);
            self.vs.data.filterIn.splice(index, 1);
            index--;
            continue;
         }

         if(orgI.indexOf(":" + self.vs.data.filterIn[index].org + ":") == -1){
            self.vs.data.filterOut.push(self.vs.data.filterIn[index]);
            self.vs.data.filterIn.splice(index, 1);
            index--;
            continue;
         }

         if(stI.indexOf(":" + self.vs.data.filterIn[index].sample_type + ":") == -1) {
            self.vs.data.filterOut.push(self.vs.data.filterIn[index]);
            self.vs.data.filterIn.splice(index, 1);
            index--;
            continue;
         }

         if(self.vs.data.filterIn[index].tests.length == 0){//no tests done
            if(self.vs.filters.tests.length > 0){//test are part of the filter
               self.vs.data.filterOut.push(self.vs.data.filterIn[index]);
               self.vs.data.filterIn.splice(index, 1);
               index--;
               continue;
            }
            if(self.vs.filters.results.length > 0){//results are part of the filter
               self.vs.data.filterOut.push(self.vs.data.filterIn[index]);
               self.vs.data.filterIn.splice(index, 1);
               index--;
               continue;
            }
         }
         else {//at least one test done
            var sampleTestIndex = "";
            var sampleResIndex = "";

            /*
             * If user has added at least one test to the filter list, only add results to sampleResIndex from tests that are in the filter list
             */

            var specResults = false;
            if(self.vs.filters.tests.length > 0) specResults = true;

            for(var tIndex = 0; tIndex < self.vs.data.filterIn[index].tests.length; tIndex++){
               sampleTestIndex = sampleTestIndex + ":" + self.vs.data.filterIn[index].tests[tIndex].test + ":";
               if(specResults == false){
                  sampleResIndex = sampleResIndex + ":" + self.vs.data.filterIn[index].tests[tIndex].result + ":";
               }
               else {//user has added at least one test to the filter list, add result to sampleResIndex only if current test is in filter list
                  if(testI.indexOf(":" + self.vs.data.filterIn[index].tests[tIndex].test + ":") != -1){
                     sampleResIndex = sampleResIndex + ":" + self.vs.data.filterIn[index].tests[tIndex].result + ":";
                  }
               }
            }

            //look for a filter is in sample's test done
            var passed = false;
            for(var tIndex = 0; tIndex < self.vs.filters.tests.length; tIndex++){
               if(sampleTestIndex.indexOf(":" + self.vs.filters.tests[tIndex] + ":") != -1){
                  //one of the filter tests is in sample's tests done
                  //console.log("found 1");
                  passed = true;
                  break;
               }
            }

            if(passed == false){//none of the tests done to sample in filter list
               self.vs.data.filterOut.push(self.vs.data.filterIn[index]);
               self.vs.data.filterIn.splice(index, 1);
               index--;
               continue;
            }

            passed = false;
            for(var rIndex = 0; rIndex < self.vs.filters.results.length; rIndex++){
               if(sampleResIndex.indexOf(":" + self.vs.filters.results[rIndex] + ":") != -1) {
                  //one of the filter results is in sample results
                  //console.log("found 2");
                  passed = true;
                  break;
               }
            }

            if(passed == false){
               self.vs.data.filterOut.push(self.vs.data.filterIn[index]);
               self.vs.data.filterIn.splice(index, 1);
               index--;
               continue;
            }
         }

         //if has reached this point then it passes all curr sample passes all filters
         //add to histogram
         var sampleDate = self.vs.data.filterIn[index].date_created.split(" ")[0];//get only the date and discard the time
         var unixTimestamp = new Date(sampleDate).getTime();

         if(typeof histogram[unixTimestamp] == 'undefined') {
            histogram[unixTimestamp] = 1;
         }
         else {
            histogram[unixTimestamp]++;
         }
      }
   }
   
   console.log("filterIn katikati"+ self.vs.data.filterIn.length);
   console.log("filterOut katikati"+ self.vs.data.filterOut.length);
   
   //console.log("size of filterIn katikati = ", self.vs.data.filterIn.length);
   //console.log("size of filterOut katikati = ", self.vs.data.filterOut.length);
   if(checkFilterIn == null || checkFilterIn == false){
      console.log("checking filter out array");
      for(var index = 0; index < self.vs.data.filterOut.length; index++){

         if(self.vs.filters.projects.length > 0 && projI.indexOf(":" + self.vs.data.filterOut[index].Project + ":") == -1 ){//sample's project not part of filter
            ////console.log(":" + self.vs.data.filterOut[index].Project + ":");
            continue;
         }
         if(self.vs.filters.organisms.length > 0 && orgI.indexOf(":" + self.vs.data.filterOut[index].org + ":") == -1){
            ////console.log(":" + self.vs.data.filterOut[index].org + ":");
            continue;
         }
         if(self.vs.filters.sampleTypes.length > 0 && stI.indexOf(":" + self.vs.data.filterOut[index].sample_type + ":") == -1){
            continue;
         }

         if(self.vs.data.filterOut[index].tests.length == 0){//no test done on this sample
            if(self.vs.filters.tests.length > 0){
               continue;
            }
            if(self.vs.filters.results.length > 0){
               continue;
            }
         }
         else {//sample with at least one test
            ////console.log("at least one test done on ", self.vs.data.filterOut[index]);
            var sampleTestIndex = "";
            var sampleResIndex = "";

            /*
             * If user has added at least one test to the filter list, only add results to sampleResIndex from tests that are in the filter list
             */

            var specResults = false;
            if(self.vs.filters.tests.length > 0) specResults = true;

            for(var tIndex = 0; tIndex < self.vs.data.filterOut[index].tests.length; tIndex++){
               sampleTestIndex = sampleTestIndex + ":" + self.vs.data.filterOut[index].tests[tIndex].test + ":";
               if(specResults == false){
                  sampleResIndex = sampleResIndex + ":" + self.vs.data.filterOut[index].tests[tIndex].result + ":";
               }
               else {//user has added at least one test to the filter list, add result to sampleResIndex only if current test is in filter list
                  if(testI.indexOf(":" + self.vs.data.filterOut[index].tests[tIndex].test + ":") != -1){
                     sampleResIndex = sampleResIndex + ":" + self.vs.data.filterOut[index].tests[tIndex].result + ":";
                  }
               }
            }

            //look for a test in the filter array that is in sampleTestIndex and move to next sample if none exists
            var passed = false;
            for(var tIndex = 0; tIndex < self.vs.filters.tests.length; tIndex++){
               if(sampleTestIndex.indexOf(":" + self.vs.filters.tests[tIndex] + ":") != -1){
                  //one of the filter tests in sample tests
                  //console.log("found 3");
                  passed = true;
                  break;
               }
            }

            if(passed == false && self.vs.filters.tests.length > 0){//sample does not have one of the tests in filter list
               continue;
            }

            passed = false;
            for(var rIndex = 0; rIndex < self.vs.filters.results.length; rIndex++){
               if(sampleResIndex.indexOf(":" + self.vs.filters.results[rIndex] + ":") != -1) {
                  //one of the filter results in sample results
                  //console.log("found 4");
                  passed = true;
                  break;
               }
            }


            if(passed == false && self.vs.filters.results.length > 0){//none of the test done to sample in filter list
               continue;
            }
         }

         //if we have reached this far, it means the sample passes all filters
         var allFilters = self.vs.filters.organisms.length + self.vs.filters.projects.length + self.vs.filters.sampleTypes.length + self.vs.filters.tests.length + self.vs.filters.results.length;

         if(allFilters > 0){
            self.vs.data.filterIn.push(self.vs.data.filterOut[index]);

            //add to histogram
            var sampleDate = self.vs.data.filterOut[index].date_created.split(" ")[0];//get only the date and discard the time
            var unixTimestamp = new Date(sampleDate).getTime();

            if(typeof histogram[unixTimestamp] == 'undefined') {
               histogram[unixTimestamp] = 1;
            }
            else {
               histogram[unixTimestamp]++;
            }

            self.vs.data.filterOut.splice(index, 1);
            index--;
         }
      }
   }
   
   console.log("filterIn after"+ self.vs.data.filterIn.length);
   console.log("filterOut after"+ self.vs.data.filterOut.length);
   
   var message = {
      data: self.vs.data,
      filters: self.vs.filters,
      histogram: histogram
   };
   
   postMessage(JSON.stringify(message));
});