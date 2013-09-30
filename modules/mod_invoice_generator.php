<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN'>
<html>
   <head>
      <title>Invoice</title>
      <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,700' rel='stylesheet' type='text/css'> 
   </head>
   <body style='font-family:Open Sans,sans-serif'>
      <div style='position: absolute; top: 10px; left: 20px; width: 500px'>
         <img src='../images/WTPlogo.jpg' style='width: 100px; height: 100px;'/>
         <h1 style='position: absolute; top: 20px; left: 120px;'>ILRI Biorepository</h1>
      </div>
      <div style='position: absolute; top: 30px; left: 600px; text-align: right; width: 300px;'>
         <h2>Invoice</h2>
         <h4>Invoice no : <?php echo $_GET['row_no'];?></h4>
      </div>
      <div style='position: absolute; top: 150px; left: 50px;'>
         <p style='font-weight: bold;'>Lab 2<br/>P.O. Box 30709<br/>Nairobi 00100, KENYA.</p>
      </div>
      <div style='position: absolute; top: 280px; left: 50px;'>
         <p>INTERNATIONAL LIVESTOCK RESEARCH INSTITUTE<br/><br/>ACCOUNTS PAYABLE<br/>P.O. BOX 30709<br/>NAIROBI<br/>00100<br/>KENYA</p>
      </div>
      <div style='position: absolute; top: 260px; left: 650px'>
         <table border='2' bordercolor = '000000'>
            <tr><td style='width: 120px;'>Date:</td><td style='width: 200px;'><?php echo $_GET['date'];?></td></tr>
            <tr><td>Contact: </td><td>Absolomon Kihara</td></tr>
            <tr><td>Telephone: </td><td>+254 20 422 3000</td></tr>
            <tr><td>Email: </td><td>a.kihara@cgiar.org</td></tr>
         </table>
      </div>
      <div style='position: absolute; top: 500px; left: 80px; width: 800px;'>
         <table>
            <tr>
               <th width='550' height='30' style='text-align: left;'>Description</th><th width='250'>Quantity</th><th width='250'>Unit Price</th><th width='250'>Net Price</th>
            </tr>
            <tr>
               <td>Nitrogen</td><td style='text-align: center;'><?php echo $_GET['amount'].' (KGs)';?></td><td style='text-align: center;'><?php echo '$'.$_GET['unit_price'];?></td><td style='text-align: center;'><?php echo '$'.$_GET['unit_price']*$_GET['amount'];?></td>
            </tr>
            <tr><td height='40' colspan='3' style='text-align: right'>Total Amount Due</td><td style='text-align: center;'><?php echo '$'.$_GET['unit_price']*$_GET['amount'];?></td></tr>
         </table>
      </div>
   </body>
</html>