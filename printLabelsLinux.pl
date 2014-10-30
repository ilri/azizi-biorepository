#!/usr/bin/perl  -w
use strict;
use CGI qw/:standard/;
use CGI::Carp qw(fatalsToBrowser);
use Data::Dumper;
use Excel::Writer::XLSX;
#use CGI::UploadEasy;

#check that the input data is valid
my $type = $ARGV[0];
my %params;
my @labels;
my $paramsCount;
my $selectedLabelType;

#check that we have the right number of params before we continue
$paramsCount = @ARGV;
if($paramsCount != 5 && $paramsCount != 8){
	print "Insufficient parameters provided when printing the labels. Expecting 5 or 8 parameters depending on the type of labels being generated.\n";
	exit 1;
}

#since we have the right number of params, lets start processing them
$params{'sequence'} = $ARGV[0];
$params{'purpose'} = $ARGV[1];
$params{'type'} = $ARGV[2];
$params{'outfile'} = $ARGV[3];
$params{'length'} = $ARGV[7];

#check if we have the correct data
if($params{'sequence'} !~ /^sequential|random$/){
	print "Invalid sequence type specified. Expecting either sequential or random.\n";
	exit 1;
}

if($params{'purpose'} !~ /^final|testing$/){
	print "Invalid purpose of the labels specified. Expecting either final or testing.\n";
	exit 1;
}

if($params{'type'} !~ /^\d+$/){
	print "Invalid label type specified. Expecting a digit.\n";
	exit 1;
}
my $barcodeParams = getConfig();

#i vsyo nachinaetsya
hseCleaning();

sub hseCleaning{
	$barcodeParams = getLabelParams($barcodeParams);
	my $time = time;
	$time =~ s/\s//g;
	$barcodeParams->{'outfile'} = $params{'outfile'};
	$barcodeParams->{'workbook'} = Excel::Writer::XLSX->new( $barcodeParams->{'outfile'} );
	my $sheetName = ($params{'sequence'} eq 'random' ) ? 'Random' : 'Sequential';
	$sheetName .= ($params{'purpose'} eq 'final' ) ? '_Final' : '_Testing';
	$barcodeParams->{'sheet'} = $barcodeParams->{'workbook'}->add_worksheet($sheetName .'_Labels');
	$barcodeParams->{'barcodeFormat'} = $barcodeParams->{'workbook'}->add_format();
	$barcodeParams->{'normalFormat'} = $barcodeParams->{'workbook'}->add_format();
	formatWorksheet();
	
	#seems all is ok, now get the other params
	if($params{'sequence'} eq 'random'){
		$params{'file'} = $ARGV[4];
		printRandomLabels();
	}
	else{	#if its not random, it is definetely sequential
		$params{'prefix'} = uc $ARGV[4];
		$params{'lastLabel'} = $ARGV[5];
		$params{'count'} = $ARGV[6];
		
		#Error checking, just as the gospel prescribes
		if($params{'prefix'} !~ /^[a-z]{3,4}$/i){
			print "Invalid label prefix '". $params{'prefix'} ."' specified. The label prefix can only be comprised of 3-4 characters only.\n";
			exit 1;
		}
		
		#last label
		if($params{'lastLabel'} !~ /^\d{1,6}$/ || $params{'lastLabel'} < 0 || $params{'lastLabel'} > 999999){
			print "Invalid last label specified. The last label can only be a digit between 1 to 999999.\n";
			exit 1;
		}
		
		#last label
		if($params{'count'} !~ /^\d{1,4}$/ || $params{'count'} < 0 || $params{'count'} > 9999){
			print "Invalid label count. We have only allowed printing of 1 to 10,000 labels at a go!\n";
			exit 1;
		}
		printSequentialLabels();
	}
	$barcodeParams->{'workbook'}->close();
}

#Now lets print the random labels in the file
sub printRandomLabels{
	# Open the input file and read the labels in that file
	my $file;
	my $length;
	my @samples;
	my $sheet;

	$file = $params{'file'};
	if(!open (UPLOAD, "<$file")){
		print "Error! The input file '$file' could not be found!\n";
		exit 1;
	}
	while (<UPLOAD>) {
		my $line = $_;
		if($line){
			$length += length($_);
		}
		if ($length > 512000) {
			die "That file is too big. The limit is 500K.";
		}
		my @line = split(/\s/,$line); 
		foreach my $i (0..$#line){
			$line[$i] =~ s/\s//g;
			push(@samples, $line[$i]);
		}
	}
	close(UPLOAD);
	
	#Now its time to do the actual printing.....
	$barcodeParams->{'samples'} = \@samples;
	my $samplesCount = @samples;
	#print $samplesCount;
	$params{'count'} = $samplesCount;
	printLabels();
}

#Printing the sequential labels...
sub printSequentialLabels{
	my $sheet = $barcodeParams->{'sheet'};
	
	my $row = 0;
	my $col = 0;
	my $lastRow = 0;
	my $curLabel = $params{'lastLabel'};
	my $pad_len = $params{'length'} - length $params{'prefix'};
	my @samples;
	
	for(my $i = 0; $i < $params{'count'}; $i++){
		$lastRow += 1;
		$curLabel += 1;
		my $padded = sprintf("%0${pad_len}d", $curLabel);
		
		my $barcode = $params{'prefix'} . $padded;
		push(@samples, $barcode);
	}
	
	$barcodeParams->{'samples'} = \@samples;
	printLabels();
}

sub printLabels{
	#now lets start the art of printing the labels
	my $sheet = $barcodeParams->{'sheet'};
	my $row = 0;
	my $col = 0;
	my $lastRow = 0;
	my @samples = $barcodeParams->{'samples'};
	
	foreach my $i (0..$params{'count'} -1 ){
		$lastRow += 1;
		my $barcode = $samples[0][$i];
		my ($code, $check, $checksum) = enCode128BC($barcode);
		$sheet->write($row, $col, "$code");				#write the barcode
		$sheet->write($row + 1, $col, "$barcode");		#write the human printable barcode
		$col += 1;
		
		$sheet->set_row($row, $barcodeParams->{'barcodeHeight'}, $barcodeParams->{'barcodeFormat'}); #set barcode row height
		if((($row + 2) % 8) == 0){ #last row, set its special height
			$sheet->set_row($row + 1, $barcodeParams->{'last_row_height'}, $barcodeParams->{'normalFormat'}); #set row size
			$lastRow = 0;
		}
		else{
			$sheet->set_row($row + 1, $barcodeParams->{'informationHeight'}, $barcodeParams->{'normalFormat'}); #set row size
		}
		#check whether we are in the last column, and if we are, set the column height accordingly
		if($col % $barcodeParams->{'labelsPerRow'} == 0){
			$col = 0;
			$row += 2;
		}
	}
}

######################################################################
sub getConfig{
	my %barcodeParams;
	my $barcodeConfig = "/usr/share/php/include/labelsConfig.txt";
	#my $barcodeConfig = "labelConfig.txt";
	open (CONFIG_FH, $barcodeConfig ) || die "Cannot open $barcodeConfig $!:";
	while(<CONFIG_FH>){
		if(m/^#/){next}
		unless(m/=/){next}
		s/\s//g;
		my ($key, $value) = split(/=/, $_);
		if($key && $value){
			$barcodeParams{$key} = $value;
			
		}
	}
	close(CONFIG_FH);
	return(\%barcodeParams);
}


#################################################################################

#################################################################################
sub enCode128BC {
	#Encode barcode 128 with mixed B and C coding
	#See http://www.idautomation.com/code128faq.html#Code-128CharacterSet for list of ascii codes
	#The encoding here is specific for the fonts supplied by idautomation and will not work with fonts
	#from other providers who may use different stop and start codons.
	my $string = shift;
	$string =~ s/\s//g;
	my @string = split('',$string);
	my $checksum = 104;
	my $flag = 'codeB';
	my $i = 0;
	while ($i <= $#string){
		if($flag eq 'codeB' && substr($string,$i) =~ m/(^\d{6,}?)/){
			$flag = 'codeC';
			#Insert CodeC start into string
			splice (@string,$i,0,chr(199));
			$checksum += (99 * ($i+1));
			$i++;
			my $n = 0;
			my $number = substr($1,$n,2);
			my $Cstring;
			my $pos = $i;
			while(length($number) == 2){
				$checksum += (($number) * ($pos + 1));
				my $ascii = getAscii($number);
				$Cstring .= chr($ascii);
				$n += 2;				
				$number = substr($1,$n,2);
				$pos++;
			}
			splice (@string,$i,2*length($Cstring),split('',$Cstring));	
			$i+= (length($Cstring));
		}
		elsif($flag eq 'codeC'){
			$flag = 'codeB';
			#Insert CodeB start into string
			splice (@string,$i,0,chr(200));
			$checksum += (100 * ($i+1));
			$i++;
			$checksum += ((ord($string[$i]) - 32) * ($i + 1));
			$i++;
		}		
		else{
			$checksum += ((ord($string[$i]) - 32) * ($i + 1));
			$i++;
			$flag = 'codeB';
		}
	}
	$checksum = $checksum % 103;
	$checksum = getAscii($checksum);
	my $check = chr($checksum );
	$string = join('',@string);
	$string = chr(204) . $string . $check . chr(206);
	return ($string, $check, $checksum);
}#################################################################################

sub enCode128B {
	#Encode barcode 128 hard coded for code B at present
	#See http://www.idautomation.com/code128faq.html#Code-128CharacterSet for list of ascii codes
	#The encoding here is specific for the fonts supplied by idautomation and will not work with fonts
	#from other providers who may use different stop and start codons.
	my $string = shift;
	$string =~ s/\s//g;
	#$string = substr($string,0, -2);
	my @string = split('',$string);
	my $checksum = 104;
	foreach my $i (0..$#string){
		my $ord = ord($string[$i]) - 32;
		$checksum += ($ord * ($i + 1));
	}
	$checksum = $checksum % 103;
	$checksum = getAscii($checksum);
	my $check = chr($checksum );
	$string = chr(204) . $string . $check . chr(206);
	return ($string, $check, $checksum);
}
############################################

sub getAscii{
	my $checksum = shift;
	if($checksum == 0){$checksum = 194}
	elsif($checksum > 94){ $checksum += 100}
	else{$checksum += 32}
	return($checksum);
}
############################################

sub old_getLabelParams{
	my $barcodeParams = shift;
	my $db = connect_db($barcodeParams->{'config_file'});
	my $querystr = "select param, value from labels where id='$selectedLabelType'";
	my $mysql_query = run_query($querystr,$db);
	while(my ($param, $value) = $mysql_query->fetchrow_array()){
		$barcodeParams->{$param} = $value;
	}
	$barcodeParams->{'barcodeFont'} = $db->selectrow_array("select font from fonts where id = $barcodeParams->{'barcodeFont'}");
	$barcodeParams->{'normalFont'} = $db->selectrow_array("select font from fonts where id = $barcodeParams->{'font'}");
	return $barcodeParams;
}

#####################################################################################

sub getLabelParams{
	my $barcodeParams = shift;
	my $db = connect_db($barcodeParams->{'config_file'});
	my $querystr = "select * from labels_settings where id='". $params{'type'} ."'";
	#die $querystr;
	
	my $mysql_query = run_query($querystr,$db);
	my $ref;
	if($ref = $mysql_query->fetchrow_hashref() ){
		#dont do this at home
		$barcodeParams->{'label_type'} = $ref->{'label_type'};
		$barcodeParams->{'barcodeHeight'} = $ref->{'barcodeHeight'};
		$barcodeParams->{'barcodeFontSize'} = $ref->{'barcodeFontSize'};
		$barcodeParams->{'informationHeight'} = $ref->{'informationHeight'};
		$barcodeParams->{'width'} = $ref->{'width'};
		$barcodeParams->{'labelsPerRow'} = $ref->{'labelsPerRow'};
		$barcodeParams->{'font'} = $ref->{'font'};
		$barcodeParams->{'InformationFontSize'} = $ref->{'InformationFontSize'};
		$barcodeParams->{'LeftRightMargin'} = $ref->{'LeftRightMargin'};
		$barcodeParams->{'TopBottomMargin'} = $ref->{'TopBottomMargin'};
		$barcodeParams->{'barcode_length'} = $ref->{'barcode_length'};
		$barcodeParams->{'shrink_to_fit'} = $ref->{'shrink_to_fit'};
		$barcodeParams->{'margin'} = $ref->{'margin'};
		$barcodeParams->{'barcodeFont'} = $ref->{'barcodeFont'};
		$barcodeParams->{'last_row_height'} = $ref->{'last_row_height'};

		#push($barcodeParams, $ref);
		#$barcodeParams = @barcodeParams;
		#$barcodeParams = ($barcodeParams, $ref);
		$barcodeParams->{'barcodeFont'} = $db->selectrow_array("select font from fonts where id = $barcodeParams->{'barcodeFont'}");
		$barcodeParams->{'normalFont'} = $db->selectrow_array("select font from fonts where id = $barcodeParams->{'font'}");
		return $barcodeParams;
	}
	else{
		die "Could not get barcode parameters from database\n";
	}
	
}
#####################################################################################

sub connect_db{
# Import database module
use DBI;
my $configFile = shift;
open (CONFIG_FH,  $configFile) || die "Cannot open $configFile $!:";

my @parameters;
while(<CONFIG_FH>){
	my $line = $_;
	unless ($line =~ m/=/){next}
	my ($key, $value) = split(/=/, $line);
	$value =~ s/\'//g;
	$value =~ s/\s//g;
	$value = substr($value, 0, index($value,";"));
	 push (@parameters, $value);
}
close(CONFIG_FH);
# Declare and initialise database variables
my $host = "$parameters[0]";
my $user = "$parameters[1]";
my $pass = "$parameters[2]";
my $name = "$parameters[3]";

# Connect to the database
# If RaiseError is set, any error message from accessing the database is stored in the variable $DBI::errstr
# If AutoCommit is not set, this allows us to rollback transactions, but we have to explicitly commit our changes to the database (this also requires a database with tables that support transactions - SNPLAD supports these)

my $dsn = "DBI:mysql:database=$name;host=$host";
my $database = DBI->connect     ($dsn,
                                $user,
                                $pass,
                                {
                                RaiseError => 1,
                                AutoCommit => 1
                                }
                        ) || die ("Error in connecting to database $DBI::errstr\n");

return $database;
}
######################################################################
#subroutine to run queries

sub run_query{
	my $querystr =shift;
	my $db = shift;
 
	# Prepare the query
	my  $query = $db->prepare($querystr);
	# Run the query, checking that it ran correctly
	unless($query->execute()) {
		my $error = "Error in executing database query $DBI::errstr";
	}
	return $query;
}
####################################################################
################################################################################


sub error_message{
		my $q = new CGI;

my $message = shift;	#create graph
	print $q->header(-type =>"text/html"),
	$q->start_html(-title=>'Label Printing',
                            -author=>'harry@liv.ac.uk',
                              );#
			print h2("$message",hr);
#		}
	;
	print $q->end_html;

}
################################################################################
###########################################################
sub cleanup{
  # scans for obsolete sessions and deletes them
	my $image_write_path = shift;
	my $t=time();
	my $timesecs = $t - 600;
	
	# delete old image files 
	opendir(IMDIR,$image_write_path) || die "Failed to open  $image_write_path $!\n";
	#$image_path = $image_path . "\\";
	
	my @files = readdir IMDIR;
	foreach (@files){
		my $file_date = (stat("$image_write_path/$_"))[9]; # date of last mod to the file in epoch seconds
		unlink "$image_write_path/$_" if ((/^Label/) && ($file_date < $timesecs))
    }
}
##
######################################################################

#Format the worksheet depending on the set parameters
sub formatWorksheet{
	my $sheet = $barcodeParams->{'sheet'};
	$sheet->set_portrait();
	$sheet->set_margins_LR($barcodeParams->{'LeftRightMargin'}/25.4);
	$sheet->set_margins_TB($barcodeParams->{'TopBottomMargin'}/25.4);
	$sheet->hide_gridlines(1);
	
	my $barCodeFormat = $barcodeParams->{'barcodeFormat'};
	$barCodeFormat->set_font($barcodeParams->{'barcodeFont'});
	if($params{'purpose'} eq 'testing' ) { $barCodeFormat->set_font_strikeout(1); }
	$barCodeFormat->set_size($barcodeParams->{'barcodeFontSize'});
	$barCodeFormat->set_align('top');
	$barCodeFormat->set_align('center');
	
	my $normalFormat = $barcodeParams->{'normalFormat'};
	$normalFormat->set_font($barcodeParams->{'normalFont'});
	if($params{'purpose'} eq 'testing' ) { $normalFormat->set_font_strikeout(1); }
	if($params{'purpose'} eq 'testing' ) { $normalFormat->set_italic(1); }
	if($params{'purpose'} eq 'testing' ) { $normalFormat->set_underline(2); }
	$normalFormat->set_size($barcodeParams->{'InformationFontSize'});
	$normalFormat->set_align('top');
	$normalFormat->set_align('center');
	$normalFormat->set_text_wrap();
	
	#Set columns  width
	$sheet->set_column(0, $barcodeParams->{'labelsPerRow'}-1, $barcodeParams->{'width'} );
}
