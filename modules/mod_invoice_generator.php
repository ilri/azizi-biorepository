<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN'>
<html style='color: #333333;'>
   <head>
      <title>Invoice</title>
      <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,700' rel='stylesheet' type='text/css'>
      <style type='text/css'>
         .invoiceTable td, .invoiceTable th {
            border: 1px solid #333333;
         }
      </style>
   </head>
   <body style='font-family:Open Sans,sans-serif'>
      <div style='position: absolute; top: 10px; left: 250px; width: 450px'>
         <img src='../images/WTPlogo.jpg' style='width: 100px; height: 100px;'/>
         <h1 style='position: absolute; top: 20px; left: 120px;'>ILRI Biorepository</h1>
      </div>
      <div style='position: absolute; top: 95px; left: 325px;'>
         <p style='font-size: 13px;'>Proper sample storage with high quality metadata</p>
      </div>
      <div style='position: absolute; top: 30px; left: 700px; text-align: right; width: 300px;'>
         <h6>Invoice <?php echo $_GET['row_no'];?></h6>
         <h6><?php echo $_GET['date'].', '.$_GET['time'];?></h6>
      </div>
      
      <div style='position: absolute; top: 200px; left: 100px; width: 800px;'>
         <table cellpadding='1' style='border: 1px solid #333333; border-collapse: collapse;' class='invoiceTable'>
            <tr style='background-color: #b0b6f1;'>
               <th width='550' height='40' style='text-align: left; padding-left: 20px;'>Description</th><th width='250'>Quantity</th><th width='250'>Unit Price</th><th width='250'>Net Price</th>
            </tr>
            <tr style='background-color: #e0e1ec;'>
               <td style='padding-left: 35px;' height='30'>Nitrogen</td><td style='text-align: center;'><?php echo $_GET['amount'].' (KGs)';?></td><td style='text-align: center;'><?php echo '$'.$_GET['unit_price'];?></td><td style='text-align: center;'><?php echo '$'.$_GET['unit_price']*$_GET['amount'];?></td>
            </tr>
            <tr style='color: #b475ad;'><td height='40' colspan='3' style='text-align: right; padding-right: 20px;'>Total Amount</td><td style='text-align: center;'><?php echo '$'.$_GET['unit_price']*$_GET['amount'];?></td></tr>
         </table>
      </div>
      <div style='position: absolute; top: 1400px; left: 700px; text-align: right; width: 300px;'>
         <p>Page 1 of 1</p>
      </div>
   </body>
</html>