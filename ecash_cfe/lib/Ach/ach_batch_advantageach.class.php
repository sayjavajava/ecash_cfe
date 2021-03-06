<?php

require_once('Mail.class.php');
/**
 * Processor specific ACH_Batch class extension
 * 
 * 	Allows for processor specific adjustments to batch format.
 *
 */
class ACH_Batch_AdvantageACH extends ACH_Batch
{
	// Record Structure for NACHA Deposit File Format
	protected static $achstruct =  array(
					      1 =>
					      array(	'record_type_code'				=> array( 1,  1, 'A', '1'),
							'priority_code'					=> array( 2,  2, 'A', '01'),
							'immediate_destination'			=> array( 4, 10, 'A', '9999999999'),
							'immediate_origin'				=> array(14, 10, 'A'),
							'file_creation_date'			=> array(24,  6, 'A'),
							'file_creation_time'			=> array(30,  4, 'A'),
							'file_id_modifier'				=> array(34,  1, 'A'),
							'record_size'					=> array(35,  3, 'A', '094'),
							'blocking_factor'				=> array(38,  2, 'A', '10'),
							'format_code'					=> array(40,  1, 'A', '1'),
							'immediate_destination_name'	=> array(41, 23, 'A', 'Processor Name'),
							'immediate_origin_name'			=> array(64, 23, 'A'),
							'reference_code'				=> array(87,  8, 'A')
							),
					      5 =>
					      array(	'record_type_code'				=> array( 1,  1, 'A', '5'),
							'service_class_code'			=> array( 2,  3, 'A', '200'),
							'company_name'					=> array( 5, 16, 'A'),
							'company_discretionary_data'	=> array(21, 20, 'A'),
							'company_identification'		=> array(41, 10, 'A'),
							'standard_entry_class_code'		=> array(51,  3, 'A', 'PPD'),
							'company_entry_description'		=> array(54, 10, 'A'),
							'company_descriptive_date'		=> array(64,  6, 'A'),
							'effective_entry_date'			=> array(70,  6, 'A'),
							'settlement_date'				=> array(76,  3, 'A', ' '),
							'originator_status_code'		=> array(79,  1, 'A', '1'),
							'originating_dfi_identification'=> array(80,  8, 'A', '99999999'),
							'batch_number'					=> array(88,  7, 'N', 1)
							),
					      6 =>
					      array(	'record_type_code'				=> array( 1,  1, 'A', '6'),
							'transaction_code'				=> array( 2,  2, 'A'),
							'receiving_dfi_identification'	=> array( 4,  8, 'A'),
							'check_digit'					=> array(12,  1, 'A'),
							'dfi_acct_number'				=> array(13, 17, 'A'),
							'amount'						=> array(30, 10, 'N'),
							'individual_identification_no'	=> array(40, 15, 'A'),
							'individual_name'				=> array(55, 22, 'A'),
							'discretionary_data'			=> array(77,  2, 'A', 'R'),
							'addenda_record_indicator'		=> array(79,  1, 'A', '0'),
							'trace_number_prefix'			=> array(80,  8, 'A', '99999999'),
							'trace_number_suffix'			=> array(88,  7, 'N')
							),
					      8 =>
					      array(	'record_type_code'				=> array( 1,  1, 'A', '8'),
							'service_class_code'			=> array( 2,  3, 'A', '200'),
							'entry_addenda_count'			=> array( 5,  6, 'N'),
							'entry_hash'					=> array(11, 10, 'N'),
							'total_debit_entry_amount'		=> array(21, 12, 'N'),
							'total_credit_entry_amount'		=> array(33, 12, 'N'),
							'company_identification'		=> array(45, 10, 'A'),
							'message_authentication_code'	=> array(55, 19, 'A', ' '),
							'reserved'						=> array(74,  6, 'A', ' '),
							'originating_dfi_identification'=> array(80,  8, 'A', '99999999'),
							'batch_number'					=> array(88,  7, 'N', 1)
							),
					      9 =>
					      array(	'record_type_code'				=> array( 1,  1, 'A', '9'),
							'batch_count'					=> array( 2,  6, 'N', 1),
							'block_count'					=> array( 8,  6, 'N'),
							'entry_addenda_count'			=> array(14,  8, 'N'),
							'entry_hash'					=> array(22, 10, 'N'),
							'total_debit_entry_amount'		=> array(32, 12, 'N'),
							'total_credit_entry_amount'		=> array(44, 12, 'N'),
							'reserved'						=> array(56, 39, 'A', ' ')
							)
					      );
					      
	
	protected $log;
	protected $company_abbrev;
	protected $company_id;
	protected $confirm_data;
					  
	public function __construct(Server $server)
	{
		parent::__construct($server);

		$this->confirmation_email_list = NULL;

		$this->confirm_data = new stdclass();
		
		// Start with sanity

		$this->confirm_data->acronym       = ECash::getConfig()->ACH_BATCH_ACRONYM;

		// These are numbers of transactions
		$this->confirm_data->total_debits  = 0;
		$this->confirm_data->total_credits = 0;

		// These are totals of transactions
		$this->confirm_data->total_debits_amount  = 0;
		$this->confirm_data->total_credits_amount = 0;

		// List is comma separated email addresses
		if (isset(ECash::getConfig()->ACH_BATCH_NOTIFY_LIST))
		{
			$this->confirm_data->notify_list   = explode(',', ECash::getConfig()->ACH_BATCH_NOTIFY_LIST);
	
			$this->confirm_data->notify_from   = (isset(ECash::getConfig()->ACH_BATCH_NOTIFY_FROM) ? ECash::getConfig()->ACH_BATCH_NOTIFY_FROM : "rebel75cell@gmail.com, brian.gillingham@gmail.com, randy.klepetko@sbcglobal.net");
		}
		else
		{
			$this->confirm_data->notify_list   = NULL;
			$this->confirm_data->notify_from   = NULL;
		}
	}
	
	
	/**
	 * Build the ACH file from an array of transactions. This is in NACHA format.
	 *
	 * @param Array $ach_transaction_ary
	 */
	protected function Build_ACH_File($ach_transaction_ary)
	{
		$phone_num = ECash::getConfig()->COMPANY_PHONE_NUMBER;
		
		//File ID Modifier is a single alpha char used to distinguish batch files from one another
		$file_id_modifier = $this->Get_Next_File_ID_Modifier();
			
		// File Header Record
		$output['immediate_origin']				= $this->ach_tax_id;
		$output['file_creation_date']			= date('ymd');
		$output['file_creation_time']			= date('Hi');
		$output['file_id_modifier']				= $file_id_modifier;
		$output['immediate_origin_name']		= substr($this->ach_company_name, 0, 23);
		$output['reference_code']				= $this->ach_batch_id;
		$output['immediate_destination_name'] 	= ECash::getConfig()->DEPOSITS_PROCESSOR_NAME;
		$output['immediate_destination']		= ECash::getConfig()->ACH_DEBIT_BANK_ABA;
		
		$this->file .= $this->Build_Record(1, $output);

		// Batch Header Record
		$output['company_name']					= substr($this->ach_company_name, 0, 16);
		$output['company_discretionary_data']	= $phone_num.' '.(($this->batch_type == 'credit') ? 'CR' : 'DR');
		$output['company_identification']		= $this->ach_company_id;
		$output['company_entry_description']	= strtoupper($this->batch_type);
		$output['company_descriptive_date']		= date('mdy', strtotime($this->batch_date));
		$output['effective_entry_date']			= date('ymd', strtotime($this->batch_date));
		$output['originating_dfi_identification'] = substr(ECash::getConfig()->ACH_DEBIT_BANK_ABA, 0, 8);

		if (!isset($this->confirm_data))
			$this->confirm_data = new StdClass();

		$this->confirm_data->total_debits_amount  = 0;
		$this->confirm_data->total_credits_amount = 0;
		$this->confirm_data->total_debits         = 0;
		$this->confirm_data->total_credits        = 0;
		
		$this->file .= $this->Build_Record(5, $output);
		
		$trace_seqno	= 0;
		$total_amount	= 0;
		$total_hash		= 0;
		$entry_addenda_count	= 0;

		// Entry Detail Records (customer)
		// Also going to build the confirmation email information in here
		foreach ($ach_transaction_ary AS $record)
		{
			// Need to filter by $record['ach_type'] credit vs. debit !!
			
			$trace_seqno++;
			
			if ($this->batch_type == 'debit')
			{
				$customer_tran_code = ($record['bank_account_type'] == 'checking') ? 27 : 37;
				$this->confirm_data->total_debits_amount += $record['amount'];
				$this->confirm_data->total_debits++;
			}
			else
			{
				$customer_tran_code = ($record['bank_account_type'] == 'checking') ? 22 : 32;
				$this->confirm_data->total_credits_amount += $record['amount'];
				$this->confirm_data->total_credits++;
			}
			
			$customer_routing_number = substr($record['bank_aba'], 0, 8);
			
			$output['transaction_code']				= $customer_tran_code;
			$output['receiving_dfi_identification']	= $customer_routing_number;
			$output['check_digit']					= $this->Check_Digit_DFI($customer_routing_number);
			$output['dfi_acct_number']				= $record['bank_account'];
			$output['amount']						= $record['amount'] * 100;
			$output['individual_identification_no']	= $record['ach_id'];
			$output['trace_number_prefix']			= substr(ECash::getConfig()->ACH_DEBIT_BANK_ABA, 0, 8);

			// Configuration option primarily used for Impact to use the application_id
			// instead of the customer's name for the individual_name field.
			if(ECash::getConfig()->ACH_USE_APP_ID_FOR_NAME === TRUE)
			{
				$output['individual_name']			= substr($record['application_id'], 0, 22);
			} else {
				$output['individual_name']			= substr($record['name'], 0, 22);
			}
			
			$output['trace_number_suffix']			= $trace_seqno;
						
			$this->file .= $this->Build_Record(6, $output);
			$this->customer_trace_numbers[$output['individual_identification_no']] = $this->Capture_Trace_Number($output);

			$total_amount	+= $output['amount'];
			$total_hash		+= $output['receiving_dfi_identification'];
			$entry_addenda_count++;
		}
		
		// Entry Detail Record  (CLK - Balancing settlement) Aka. an 'offset'
		
		$trace_seqno++;

		if ($this->batch_type == 'debit')
		{
			$clk_tran_code = ($this->ach_debit_bank_acct_type == 'checking') ? 22 : 32;
		}
		else
		{
			$clk_tran_code = ($this->ach_credit_bank_acct_type == 'checking') ? 27 : 37;
		}

		$ach_bank_aba  = ($this->batch_type == 'credit') ? $this->ach_credit_bank_aba  : $this->ach_debit_bank_aba;
		$ach_bank_acct = ($this->batch_type == 'credit') ? $this->ach_credit_bank_acct : $this->ach_debit_bank_acct;

		$clk_routing_number = substr($ach_bank_aba, 0, 8);
		
		$output['transaction_code']				= $clk_tran_code;
		$output['receiving_dfi_identification']	= $clk_routing_number;
		$output['check_digit']					= $this->Check_Digit_DFI($clk_routing_number);
		$output['dfi_acct_number']				= $ach_bank_acct;
		$output['amount']						= $total_amount;
		$output['individual_identification_no']	= $this->clk_ach_id;
		$output['individual_name']				= substr($this->ach_company_name, 0, 22);
		$output['trace_number_suffix']			= $trace_seqno;
		$output['trace_number_prefix']			= substr(ECash::getConfig()->ACH_DEBIT_BANK_ABA, 0, 8);

		// USE_ACH_ENTRY_DETAIL is used to toggle the creation of an offsetting type 6 entry
		if(ECash::getConfig()->USE_ACH_ENTRY_DETAIL) 
		{
			$total_hash += $output['receiving_dfi_identification'];
			$entry_addenda_count++;
			$this->file .= $this->Build_Record(6, $output);
		}
		
		$this->clk_trace_numbers[$output['individual_identification_no']] = $this->Capture_Trace_Number($output);
		$this->ach_utils->Set_Total_Amount($total_amount/100);

		// Batch Control Total Record
		$output['total_debit_entry_amount'] = 0;
		$output['total_credit_entry_amount'] = 0;
		$output['entry_addenda_count']			= $entry_addenda_count;
		$output['entry_hash']					= substr($total_hash, -10);
		if($this->batch_type == 'debit')
		{
			$output['total_debit_entry_amount']		= $total_amount;
		}
		else
		{
			$output['total_credit_entry_amount']	= $total_amount;
		}
		$output['company_identification']		= $this->ach_company_id;
		$output['originating_dfi_identification'] = substr(ECash::getConfig()->ACH_DEBIT_BANK_ABA, 0, 8);

		$this->file .= $this->Build_Record(8, $output);
		
		// File Control Total Record
		$output['total_debit_entry_amount'] = 0;
		$output['total_credit_entry_amount'] = 0;
		$output = array();
		$output['entry_addenda_count']			= $entry_addenda_count;
		$output['entry_hash']					= substr($total_hash, -10);
		if($this->batch_type == 'debit')
		{
			$output['total_debit_entry_amount']		= $total_amount;
		}
		else
		{
			$output['total_credit_entry_amount']	= $total_amount;
		}
		
		$output['block_count']					= (integer)ceil(($this->rowcount +1)/10);

		$this->file .= $this->Build_Record(9, $output);
		
		// Pad out to full block
		$this->Block_Pad();
	}
	
	// I'm overiding this so that I can include the confirmation email code, also encryption
	protected function Send_Batch ()
	{
		$batch_login = ECash::getConfig()->ACH_BATCH_LOGIN;
		$batch_pass = ECash::getConfig()->ACH_BATCH_PASS;
		
		try {
			$transport_type   = ECash::getConfig()->ACH_TRANSPORT_TYPE;
			$transport_url    = ECash::getConfig()->ACH_BATCH_URL;
			$transport_server = ECash::getConfig()->ACH_BATCH_SERVER;
			$transport_port   = ECash::getConfig()->ACH_BATCH_SERVER_PORT;
			
			$transport = ACHTransport::CreateTransport($transport_type, $transport_server, $batch_login, $batch_pass, $transport_port);
		
			if (EXECUTION_MODE != 'LIVE' && $transport->hasMethod('setBatchKey')) 
			{
				$transport->setBatchKey(ECash::getConfig()->ACH_BATCH_KEY);
			}
		
			$batch_response = '';

			$remote_filename = $this->Get_Remote_Filename();

			if ((isset(ECash::getConfig()->ACH_BATCH_USE_PGP)) && ECash::getConfig()->ACH_BATCH_USE_PGP == TRUE)
			{
				try
				{
					$this->PGP_Encrypt_Batch();
				}
				catch (Exception $e)
				{
					// Operate gracefully, just leave it unencrypted, log an error
					$this->log->write($e->getMessage());
				}
			}
			
			$batch_success = $transport->sendBatch($this->ach_filename, $remote_filename, $batch_response);

			// If the notify list exists, send the confirmation email to each person listed in it
			if (is_array($this->confirm_data->notify_list) && !empty($this->confirm_data->notify_list))
			{
				$pd_calc = new Pay_Date_Calc_3(Fetch_Holiday_List());

				$recipients = $this->confirm_data->notify_list;

				$mail_data['company_name']      = ECash::getConfig()->COMPANY_NAME;
				$mail_data['company_acro']      = ECash::getConfig()->ACH_BATCH_ACRONYM;
				$mail_data['date']              = date('m/d/Y');
				$mail_data['total_credits']     = $this->confirm_data->total_credits;
				$mail_data['total_debits']      = $this->confirm_data->total_debits;
				$mail_data['total_credits_amt'] = $this->confirm_data->total_credits_amount;
				$mail_data['total_debits_amt']  = $this->confirm_data->total_debits_amount;

				$mail_data['effective_date']    = $pd_calc->Get_Next_Business_Day(date('m/d/Y'));
				$mail_data['remote_file']       = $remote_filename;

				// Administrative Contact
				$mail_data['admin_contact_n']   = ECash::getConfig()->ACH_ADMIN_CONTACT_NAME;
				$mail_data['admin_contact_p']   = ECash::getConfig()->ACH_ADMIN_CONTACT_PHONE;
				$mail_data['admin_contact_e']   = ECash::getConfig()->ACH_ADMIN_CONTACT_EMAIL;

				// Technical Contact
				$mail_data['tech_contact_n']    = ECash::getConfig()->ACH_TECH_CONTACT_NAME;
				$mail_data['tech_contact_p']    = ECash::getConfig()->ACH_TECH_CONTACT_PHONE;
				$mail_data['tech_contact_e']    = ECash::getConfig()->ACH_TECH_CONTACT_EMAIL;
	
				eCash_Mail::ADVANTAGE_ACH_BATCH_CONFIRMATION($recipients, $mail_data);
			}
			
		} catch (Exception $e) {
			$this->log->write($e->getMessage());
			$batch_response = '';
			$batch_success = false;
		}
		
		if ($batch_success) {
			$batch_status = 'sent';
		} else {
			$this->log->write("ACH file send: No response from '" . $remote_filename . "'.", LOG_ERR);
			$batch_status = 'failed';
		}

		if($batch_response === TRUE)
		{
			// BC=1&DC=16194&CC=0&CA=1342686.75&DA=1342686.75&AC=0&FS=0&IC=0&REF=ECASH20061129.01&ER=0
			$bc  = $this->batch_count;       // Batch Count
			$cc  = 0;	// Credit Count
			$ca  = 0;	// Credit Amount
			$dc  = 0;	// Debit Count
			$da  = 0;	// Debit Amount
			$fs  = 0;	// File Size (bytes)
			$er  = 0;	// Error Code
			$ref = '';	// Reference Number (Intercept)
			$ac  = 0;	// Unknown
			$ic  = 0;	// Unknown

			if($this->batch_type === 'credit')
			{
				$cc = $this->confirm_data->total_credits;
				$ca = floatval($this->confirm_data->total_credits_amount);
			}
			else if ($this->batch_type === 'debit')
			{
				$dc = $this->confirm_data->total_debits;
				$da = floatval($this->confirm_data->total_debits_amount);
			}
			else
			{
				$cc = $this->confirm_data->total_credits;
				$ca = floatval($this->confirm_data->total_credits_amount);
				$dc = $this->confirm_data->total_debits;
				$da = floatval($this->confirm_data->total_debits_amount);
			}

			$batch_response = "BC=$bc&DC=$dc&CC=$cc&CA=$ca&DA=$da&AC=$ac&FS=%fs&IC=$ic&REF=$ref&ER=$er";
		}

		// Update response and corresponding status into ach_batch table,
		$this->Update_ACH_Batch_Response($batch_response, $batch_status);
		
		// Delete temp ACH file
		$this->Destroy_Local_File();
		
		// Set up return values
		$return_val = array();
		parse_str($batch_response, $return_val['intercept']);
		$return_val['status'] = $batch_status;

		return $return_val;
	}
	
	protected function Build_Record ($rectype, $value_ary)
	{
		$record = "";
		
		foreach (self::$achstruct[$rectype] as $fieldname => $attributes)
		{
			
			if (isset($value_ary[$fieldname]))
			{
				$record .= $this->Set_Field_Content($rectype, $fieldname, $value_ary[$fieldname]);
			}
			else
			{
				$record .= $this->Set_Field_Content($rectype, $fieldname);
			}
		}
		
		$record .= $this->RS;
		
		$this->rowcount++;
		
		return $record;
	}
	
	private function Set_Field_Content ($rectype, $fieldname, $value=NULL)
	{
		$result = "";
		
		if (!isset(self::$achstruct[$rectype]))
		{
			throw new General_Exception("ACH internal failure -- record type '$rectype' undefined.");
		}
		
		if (!isset(self::$achstruct[$rectype][$fieldname]))
		{
			throw new General_Exception("ACH internal failure -- field '$fieldname' undefined for record type '$rectype'.");
		}
				
		if (
		    !is_array(self::$achstruct[$rectype][$fieldname]) 	|| 
		    count(self::$achstruct[$rectype][$fieldname]) < 3 	|| 
		    !is_int(self::$achstruct[$rectype][$fieldname][0])	||
		    self::$achstruct[$rectype][$fieldname][0] < 1		||
		    self::$achstruct[$rectype][$fieldname][0] > 94		||
		    !is_int(self::$achstruct[$rectype][$fieldname][1])	||
		    (self::$achstruct[$rectype][$fieldname][0] + self::$achstruct[$rectype][$fieldname][1] - 1) > 94	||
		    !in_array(self::$achstruct[$rectype][$fieldname][2], array('A','N'))	||
		    ((isset(self::$achstruct[$rectype][$fieldname][3])	&& 
		      strlen(self::$achstruct[$rectype][$fieldname][3]) > self::$achstruct[$rectype][$fieldname][1]))
		    )
		{
			throw new General_Exception("ACH internal failure -- invalid definition for '$fieldname' in record type '$rectype'.");
		}

		if ( isset($value) && strlen($value) > self::$achstruct[$rectype][$fieldname][1] )
		{
			throw new General_Exception("ACH internal failure -- value '$value' is too long to fit in field '$fieldname' of record type '$rectype'.");
		}
		
		if ( (!isset($value) || strlen($value) == 0) && isset(self::$achstruct[$rectype][$fieldname][3]) )
		{
			$value = self::$achstruct[$rectype][$fieldname][3];
		}
		
		if (self::$achstruct[$rectype][$fieldname][2] == 'A')
		{
			$result = str_pad($value, self::$achstruct[$rectype][$fieldname][1], ' ', STR_PAD_RIGHT);
		}
		elseif (self::$achstruct[$rectype][$fieldname][2] == 'N')
		{
			$result = str_pad($value, self::$achstruct[$rectype][$fieldname][1], '0', STR_PAD_LEFT );
		}
		
		return $result;
	}
	
	private function Capture_Trace_Number ($value_ary)
	{
		$trace_number = "";
		
		foreach (self::$achstruct[6] as $fieldname => $attributes)
		{
			if ( in_array($fieldname, array('trace_number_prefix','trace_number_suffix')) )
			{
				if (isset($value_ary[$fieldname]))
				{
					$trace_number .= $this->Set_Field_Content(6, $fieldname, $value_ary[$fieldname]);
				}
				else
				{
					$trace_number .= $this->Set_Field_Content(6, $fieldname);
				}
			}
		}
				
		return $trace_number;
	}

	private function Block_Pad ()
	{
		$padrows = (10 - ($this->rowcount % 10)) % 10;
		
		for ($i = 0; $i < $padrows; $i++)
		{
			$record = str_repeat('9', 94) . $this->RS;
			$this->file .= $record;
		}

		return true;
	}
	protected function Create_Local_File ()
	{
		$tmp_file_sfx = date("YmdHis") . $this->microseconds();
		$this->ach_filename = "/tmp/ecash_{$this->company_abbrev}_" . $tmp_file_sfx . ".ach";

		try
		{	
			$fh = fopen($this->ach_filename, 'w+'); 
			fwrite($fh, utf8_encode($this->file));
			fclose($fh);
		}
		catch(Exception $e)
			{
				throw $e;
			}
		
		return true;
	}
}
?>
