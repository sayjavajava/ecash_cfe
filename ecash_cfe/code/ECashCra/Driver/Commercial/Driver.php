<?php

class ECashCra_Driver_Commercial_Driver implements ECashCra_IDriver 
{
	const FINANCE_PERCENT = 0.30;
    const PaymentTimingViewDaysWindow = 60;

	
	/**
	 * @var ECashCra_Driver_Commercial_DataBuilder
	 */
	protected $data_builder;

	/**
	 * @var ECashCra_Driver_Commercial_ApplicationQueryBuilder
	 */
	protected $application_query_builder;

	/**
	 * @var ECashCra_Driver_Commercial_PaymentQueryBuilder
	 */
	protected $payment_query_builder;

	/**
	 * @var ECashCra_Driver_Commercial_Config
	 */
	protected $config;

	/**
	 * @var Status_Utility
	 */
	protected $status_map;

	public function __construct() {
		$this->data_builder = new ECashCra_Driver_Commercial_DataBuilder;
		$this->application_query_builder = new ECashCra_Driver_Commercial_ApplicationQueryBuilder;
		$this->payment_query_builder = new ECashCra_Driver_Commercial_PaymentQueryBuilder;
		$this->config = new ECashCra_Driver_Commercial_Config;
	}

	/**
	 * Returns the password used to connect to cra
	 *
	 * @return string
	 */
	public function getCraApiConfig($cra_source, $item) {
		return $this->config->getApiConfig($cra_source, $item);
	}

	/**
	 * Returns application objects with relevant status changes
	 *
	 * Only status changes that occured on the given date should be returned.
	 *
	 * @param string $date YYYY-MM-DD
	 * @return array
	 */
	public function getStatusChanges($date) {
		// Get status history and application data from the app service database
		$app_service_data = $this->newApplicationServiceData();
		$app_data = $app_service_data->getCraStatusHistory($date, $this->config->getUpdateableStatuses(),
			self::FINANCE_PERCENT);
			
		$temp_table_columns = $this->getApplicationTempTableColumns();
		$temp_table_columns['application_status_name'] = 'VARCHAR(255)';
			
		// Create temporary table in MySQL for app service data
		ECash_DB_Util::generateTempTable(
			$this->config->getConnection(),
			ECashCra_Driver_Commercial_ApplicationQueryBuilder::STATUS_HISTORY_APPLICATION_TEMP_TABLE,
			$temp_table_columns
		);
		
		// Insert data
		ECash_DB_Util::insertIntoTempTableFromArray(
			$this->config->getConnection(),
			ECashCra_Driver_Commercial_ApplicationQueryBuilder::STATUS_HISTORY_APPLICATION_TEMP_TABLE,
			$app_data,
			$temp_table_columns
		);

		$rs = DB_Util_1::queryPrepared($this->config->getConnection(),
				$this->application_query_builder->getStatusHistoryQuery());

		$this->data_builder->attachObserver(
			new Delegate_1(array($this, 'setApplicationBalance'))
		);

		$this->data_builder->attachObserver(
			new Delegate_1(array($this, 'setStatusChain'))
		);

		return $this->data_builder->getApplicationData($rs);
	}

	/**
	 * Returns application objects with cancellations on the given date.
	 *
	 * @param string $date YYYY-MM-DD
	 * @return array
	 */
	public function getCancellations($date) {
		$trans_data = $this->getCancellationTransactions($date);
		$status_data = $this->getCancellationStatuses($date);
		return array_merge($trans_data, $status_data);
	}
	
	private function getCancellationTransactions($date) {
		$args = array();
		$trans_query = $this->application_query_builder->getCancellationTransactionsQuery($date, $this->config->getCompany(), $args);
		$trans_rs = DB_Util_1::queryPrepared($this->config->getConnection(), $trans_query, $args);
		
		$application_list = array();
		while ($row = $trans_rs->fetch())
		{
			$application_list[] = $row['application_id'];
		}
		
		$result = $this->newApplicationServiceData()->getCraApplicationData($application_list, self::FINANCE_PERCENT);
		return $this->data_builder->getApplicationData($result);
	}
	
	private function getCancellationStatuses($date) {
		$result = $this->newApplicationServiceData()->getCraCancellationStatusData($date,
				$this->config->getPrefundStatuses(), $this->config->getCancellationStatuses(), self::FINANCE_PERCENT);
		return $this->data_builder->getApplicationData($result);
	}

	/**
	 * Returns application objects with recoveries on the given date.
	 *
	 * @param string $date YYYY-MM-DD
	 * @return array
	 */
	public function getRecoveries($date) {
		$db = $this->config->getConnection();
		
		// create recoveries temp table
		$args = array();
		$query = $this->application_query_builder->getRecoveriesTempTableQuery($date, $this->config->getCompany(), $args);
		DB_Util_1::queryPrepared($db, $query, $args);
		
		// get application ID's to pull from the application service
		$query = $this->application_query_builder->getRecoveriesTempTableApplicationsQuery();
		$result = DB_Util_1::queryPrepared($db, $query);
		
		$app_id_list = array();
		foreach ($result as $row)
		{
			$app_id_list[] = (int) $row['application_id'];
		}
		unset($result);
		
		// get application data from app service and store in temp table
		$this->createApplicationServiceTemporaryTable(
				ECashCra_Driver_Commercial_ApplicationQueryBuilder::RECOVERIES_APPLICATION_TEMP_TABLE, $app_id_list,
				self::FINANCE_PERCENT);
		
		// query both temp tables to get full data
		$query = $this->application_query_builder->getRecoveriesQuery();
		$rs = DB_Util_1::queryPrepared($this->config->getConnection(), $query);

		$this->data_builder->attachObserver(
			new Delegate_1(array($this, 'setApplicationBalance'))
		);
		$this->data_builder->attachObserver(
			new Delegate_1(array($this, 'setRecoveryAmount'))
		);

		return $this->data_builder->getApplicationData($rs);
	}

	/**
	 * Returns application objects with failed re-disbursements on the given date
	 *
	 * @param string $date YYYY-MM-DD
	 * @return array
	 */
	public function getActiveStatusChanges($date)
	{
		return array_merge(
			$this->getFailedRedisbursements($date),
			$this->getStatusChangesFromInactive($date),
			$this->getActiveStatusChangesData($date)
		);
	}
	
    

	/**
	 * Returns factor trust formated application objects with new loans on the given date
	 *
	 * @param string $date YYYY-MM-DD
	 * @return array
	 */
	public function getFactorTrustNewLoans($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->getFactorTrustNewLoanQuery(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

		return $results->fetchAll();
	}
    
	public function getFactorTrustChargeoffs($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->makeFactorTrustPaymentDetailView(
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->getFactorTrustChargeoffQuery(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
		return $results->fetchAll();
	}
    
	public function getFactorTrustVoids($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->makeFactorTrustPaymentDetailView(
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->getFactorTrustVoidQuery(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
		return $results->fetchAll();
	}
    
	public function getFactorTrustRolloverLoans($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->makeFactorTrustPaymentTimingView(
		self::PaymentTimingViewDaysWindow,
		$date,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->makeFactorTrustPaymentDetailView(
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->getFactorTrustRolloverQuery(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
		return $results->fetchAll();
	}
    
	public function getFactorTrustPayments($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->makeFactorTrustPaymentTimingView(
		self::PaymentTimingViewDaysWindow,
		$date,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
		
		$query = $this->application_query_builder->makeFactorTrustPaymentDetailView(
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->getFactorTrustPaymentQuery(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
		return $results->fetchAll();
	}
        
	public function getFactorTrustOldZeroBalance($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->makeFactorTrustPaymentTimingView(
            self::PaymentTimingViewDaysWindow,
			$date,
			$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
		
		$query = $this->application_query_builder->makeFactorTrustPaymentDetailView(
			$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->getFactorTrustOldZeroBalanceQuery(
			14,  // two weeks back
			$date,
			$company,
			$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
        return $results->fetchAll();
	}
    
	public function getFactorTrustRolloverPrePayments($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->makeFactorTrustPaymentTimingView(
            self::PaymentTimingViewDaysWindow,
            $date,
			$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->makeFactorTrustPaymentDetailView(
			$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->getFactorTrustRolloverPrePayQuery(
			$date,
            $company, 
			$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
        return $results->fetchAll();
	}
    
	public function getFactorTrustReturns($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->makeFactorTrustPaymentTimingView(
		self::PaymentTimingViewDaysWindow,
		$date,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->makeFactorTrustPaymentDetailView(
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->getFactorTrustReturnQuery(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
		return $results->fetchAll();
	}
    
	public function getFactorTrustReturnVoids($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->makeFactorTrustPaymentTimingView(
            self::PaymentTimingViewDaysWindow,
            $date,
			$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->makeFactorTrustPaymentDetailView(
			$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->getFactorTrustReturnVoidQuery(
			$date,
			$company,
			$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
        return $results->fetchAll();
	}
	
	//asm 66 FT CLH reporting entity
	public function getFactorTrustNewLoans_CLH($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->getFactorTrustNewLoanQuery_CLH(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

		return $results->fetchAll();
	}
	
	public function getFactorTrustPayments_CLH($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->makeFactorTrustPaymentTimingView(
		self::PaymentTimingViewDaysWindow,
		$date,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
		
		$query = $this->application_query_builder->makeFactorTrustPaymentDetailView(
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->getFactorTrustPaymentQuery_CLH(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
		return $results->fetchAll();
	}
	
	public function getFactorTrustPaymentsDueDateMod_CLH($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->makeFactorTrustPaymentTimingView(
		self::PaymentTimingViewDaysWindow,
		$date,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
		
		$query = $this->application_query_builder->makeFactorTrustPaymentDetailView(
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->getFactorTrustPaymentDueDateModQuery_CLH(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
		return $results->fetchAll();
	}
	
	public function getFactorTrustRolloverLoans_CLH($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->makeFactorTrustPaymentTimingView(
		self::PaymentTimingViewDaysWindow,
		$date,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->makeFactorTrustPaymentDetailView(
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->getFactorTrustRolloverQuery_CLH(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
		return $results->fetchAll();
	}
	
	public function getFactorTrustRolloverLoansDueDateMod_CLH($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->makeFactorTrustPaymentTimingView(
		self::PaymentTimingViewDaysWindow,
		$date,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->makeFactorTrustPaymentDetailView(
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->getFactorTrustRolloverQueryDueDateMod_CLH(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
		return $results->fetchAll();
	}
	
	public function getFactorTrustReturns_CLH($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->makeFactorTrustPaymentTimingView(
		self::PaymentTimingViewDaysWindow,
		$date,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->makeFactorTrustPaymentDetailView(
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->getFactorTrustReturnQuery_CLH(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
		return $results->fetchAll();
	}
    
	public function getFactorTrustChargeoffs_CLH($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->makeFactorTrustPaymentDetailView(
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->getFactorTrustChargeoffQuery_CLH(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
		return $results->fetchAll();
	}
	
	public function getFactorTrustBankruptcy_CLH($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->makeFactorTrustPaymentDetailView(
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->getFactorTrustBankruptcyQuery_CLH(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
		return $results->fetchAll();
	}
    
	public function getFactorTrustVoids_CLH($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->makeFactorTrustPaymentDetailView(
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$query = $this->application_query_builder->getFactorTrustVoidQuery_CLH(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
		return $results->fetchAll();
	}
	/////////////////////////////////////////////////////////////////////
    
	public function prepFactorTrustInitialRound($set, $round, $div, $date)
	{
		$db = $this->config->getConnection();

		$args = array();
		$query = $this->application_query_builder->deleteFactorTrustApplicationSubTable(
			$args,
            'application_'.$set.'_',
            $round
        );
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
    	$args = array();
		$query = $this->application_query_builder->deleteFactorTrustApplicationSubTable(
			$args,
            'transaction_register_'.$set.'_',
            $round
        );
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$args = array();
		$query = $this->application_query_builder->deleteFactorTrustApplicationSubTable(
			$args,
            'event_schedule_'.$set.'_',
            $round
        );
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
    	$args = array();
		$query = $this->application_query_builder->deleteFactorTrustApplicationSubTable(
			$args,
            'ach_'.$set.'_',
            $round
        );
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
    	$args = array();
		$query = $this->application_query_builder->deleteFactorTrustApplicationSubTable(
			$args,
            'event_amount_'.$set.'_',
            $round
        );
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
    	$args = array();
		$query = $this->application_query_builder->createFactorTrustApplicationSubTable(
			$args,
            '_'.$set.'_'.$round
        );
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
    	$args = array();
		$query = $this->application_query_builder->createFactorTrustEventScheduleSubTable(
			$args,
            '_'.$set.'_'.$round
        );
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
    	$args = array();
		$query = $this->application_query_builder->createFactorTrustTransactionRegisterSubTable(
			$args,
            '_'.$set.'_'.$round
        );
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
    	$args = array();
		$query = $this->application_query_builder->createFactorTrustEventAmountSubTable(
			$args,
            '_'.$set.'_'.$round
        );
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
    	$args = array();
		$query = $this->application_query_builder->createFactorTrustAchSubTable(
			$args,
            '_'.$set.'_'.$round
        );
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
    	$args = array();
		$query = $this->application_query_builder->fillFactorTrustApplicationSubTable(
			$args,
            $div,
            '_'.$set,
            $round
        );
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);

        $args = array();
		$query = $this->application_query_builder->fillFactorTrustTransactionRegisterSubTables(
			$args,
            '_'.$set,
            '_'.$round
        );
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
        $args = array();
		$query = $this->application_query_builder->fillFactorTrustEventScheduleSubTables(
			$args,
            '_'.$set,
            '_'.$round
        );
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
        $args = array();
		$query = $this->application_query_builder->fillFactorTrustEventAmountSubTables(
			$args,
            '_'.$set,
            '_'.$round
        );
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
        $args = array();
		$query = $this->application_query_builder->fillFactorTrustAchSubTables(
			$args,
            '_'.$set,
            '_'.$round
        );
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$args = array();
        
		$query = $this->application_query_builder->makeFactorTrustPaymentDetailView(
			$args,
            '_'.$set.'_'.$round
		);
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
        
		$args = array();
        
		$query = $this->application_query_builder->makeFactorTrustEventAmountView(
			$args,
            '_'.$set.'_'.$round
		);
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);
                
		$args = array();
		
		$query = $this->application_query_builder->makeFactorTrustPaymentTimingView(
            self::PaymentTimingViewDaysWindow,
            $date,
			$args,
            '_'.$set.'_'.$round
		);
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		unset($results);

        return true;
	}
        
	public function getFactorTrustActiveLoans($date, $num = '')
	{
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		$args = array();

		$query = $this->application_query_builder->getFactorTrustActiveLoanQuery(
            $num,
            $date,
			$company,
			$args
		);
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
        return $results->fetchAll();
	}
    
	public function getFactorTrustActiveLoanPaymentsBalance($date, $num = '')
	{
        
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		$args = array();
        
		$query = $this->application_query_builder->getFactorTrustActiveLoanPaymentsBalanceQuery(
			$date,
			$company,
			$args
		);
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
        return $results->fetchAll();
	}
    
	public function getFactorTrustActiveLoanPaymentsTiming($date, $num = '')
	{
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		$args = array();

        
		$query = $this->application_query_builder->getFactorTrustActiveLoanPaymentsTimingQuery(
			$date,
			$company,
			$args
		);
//print_r($query."\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
        
        return $results->fetchAll();
	}
     
    private function getNewLoanApplicationIDs($date){
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		$args = array();
		
		$query = $this->application_query_builder->getNewLoanApplicationIDQuery(
			$date,
			$company,
			$this->getActiveStatusId(),
			$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
     
		$application_id_list = array();
		while ($row = $results->fetch(DB_IStatement_1::FETCH_ASSOC)) $application_id_list[] = $row['application_id'];
		unset($results);
        
		return $application_id_list;
    }
    
    private function getLoanPaymentApplicationIDs($date){
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		$args = array();
		
		$query = $this->application_query_builder->getLoanPaymentApplicationIDQuery(
			$date,
			$company,
			$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
     
		$application_id_list = array();
		while ($row = $results->fetch(DB_IStatement_1::FETCH_ASSOC)) $application_id_list[] = $row['application_id'];
		unset($results);
        
		return $application_id_list;
    }
    
	private function getFailedRedisbursements($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		// Get application ID's that we need to get data for from the application service
		$query = $this->application_query_builder->getFailedRedisbursementsApplicationsQuery($date, $company, $args);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);
		
		$application_id_list = array();
		while ($row = $results->fetch(DB_IStatement_1::FETCH_ASSOC)) $application_id_list[] = $row['application_id'];
		unset($results);
		
		// Pull the data from the application service and stick in temp table
		$this->createApplicationServiceTemporaryTable(
			ECashCra_Driver_Commercial_ApplicationQueryBuilder::FAILED_REDISBURSEMENTS_APPLICATION_TEMP_TABLE,
			$application_id_list, self::FINANCE_PERCENT);
		unset($application_id_list);
		
		$results = DB_Util_1::queryPrepared($db, $this->application_query_builder->getFailedRedisbursementsQuery());
		return $this->data_builder->getApplicationData($results);
	}
	
	private function getStatusChangesFromInactive($date)
	{
		$app_service_data = $this->newApplicationServiceData();
		
		return $this->data_builder->getApplicationData($app_service_data->getCraStatusChangesFromInactive($date,
			self::FINANCE_PERCENT));
	}
	
	private function getActiveStatusChangesData($date)
	{
		$app_service_data = $this->newApplicationServiceData();
		
		return $this->data_builder->getApplicationData(
			$app_service_data->getCraActiveStatusChanges(
				$date,
				$this->config->getActiveStatus(),
				$this->config->getCancellationStatuses(),
				self::FINANCE_PERCENT
			)
		);
	}

	/**
	 * Returns application objects that are reacts and were funded on the given
	 * date.
	 *
	 * @param string $date YYYY-MM-DD
	 * @return array
	 */
	public function getFundedReacts($date)
	{
		$args = array();
		$query = $this->application_query_builder->getFundedReacts(
			$date,
			$this->config->getCompany(),
			$this->getActiveStatusId(),
			$args
		);

		$rs = DB_Util_1::queryPrepared($this->config->getConnection(), $query, $args);

		return $this->data_builder->getApplicationData($rs);
	}

	/**
	 * Returns payment objects for all payments made on the given date.
	 *
	 * @param string $date YYYY-MM-DD
	 * @return array
	 */
	public function getPayments($date)
	{
		return array_merge(
			$this->getNonAchPayments($date),
			$this->getAchFailures($date),
			$this->getAchPayments($date)
		);
	}

	protected function getNonAchPayments($date)
	{
		$args = array();
		
		// Setup the temporary table with the transaction_history
		$query = $this->payment_query_builder->getTemporaryTransactionHistoryQuery($data, $this->config->getCompany(),
				$args);
		DB_Util_1::queryPrepared($this->config->getConnection(), $query, $args);
		
		// Get the application_id's from the temporary table
		$results = DB_Util_1::queryPrepared($this->config->getConnection(),
				$this->payment_query_builder->getTemporaryTransactionHistoryApplicationsQuery());
		
		$application_id_list = array();
		while ($row = $results->fetch(DB_IStatement_1::FETCH_ASSOC))
		{
			$application_id_list[] = $row['application_id'];
		}
		unset($results);
		
		// Get application info from the application service's database
		$this->createApplicationServiceTemporaryTable(ECashCra_Driver_Commercial_PaymentQueryBuilder::APPLICATION_TEMP_TABLE,
			$application_id_list, self::FINANCE_PERCENT);
		
		$query = $this->payment_query_builder->getNonACHPaymentsQuery();

		$rs = DB_Util_1::queryPrepared($this->config->getConnection(), $query);

		return $this->data_builder->getPaymentData($rs);
	}
	
	/**
	 * Creates a temporary table based on application data from the application service database.
	 * 
	 * @param array $application_ids
	 * @param float $finance_charge
	 */
	private function createApplicationServiceTemporaryTable($table_name, array $application_ids, $finance_charge)
	{
		$application_data = $this->newApplicationServiceData()->getCraApplicationDataArray($application_ids, $finance_charge);
		
		$temp_table_data = array();
		foreach ($application_data as $data)
		{
			$temp_table_data[] = $data;
		}
		
		ECash_DB_Util::generateTempTableFromArray($this->config->getConnection(), $table_name, $temp_table_data,
			$this->getApplicationTempTableColumns(), 'application_id');
	}
	
	/**
	 * Returns a new ECash_Application_Data that will allow access to application service data.
	 * 
	 * @return ECash_Application_Data
	 */
	protected function newApplicationServiceData()
	{
		return new ECash_Data_Application($this->config->getConnection());
	}
	
	private function getApplicationTempTableColumns()
	{
		return array(
			'company_id' => 'INT UNSIGNED',
			'application_id' => 'INT UNSIGNED',
			'date_fund_actual' => 'DATE',
			'fund_actual' => 'DECIMAL(7,2)',
			'date_first_payment' => 'DATE',
			'fee_amount' => 'DECIMAL(7,2)',
			'employer_name' => 'VARCHAR(255)',
			'work_address_1' => 'CHAR(1)',
			'work_address_2' => 'CHAR(1)',
			'work_city' => 'CHAR(1)',
			'work_state' => 'CHAR(1)',
			'work_zip_code' => 'CHAR(1)',
			'pay_period' => 'VARCHAR(20)',
			'income_frequency' => 'VARCHAR(20)',
			'phone_work' => 'VARCHAR(10)',
			'phone_work_ext' => 'VARCHAR(10)',
			'name_first' => 'VARCHAR(255)',
			'name_middle' => 'VARCHAR(255)',
			'name_last' => 'VARCHAR(255)',
			'street' => 'VARCHAR(255)',
			'unit' => 'VARCHAR(255)',
			'city' => 'VARCHAR(255)',
			'state' => 'VARCHAR(255)',
			'zip_code' => 'VARCHAR(255)',
			'phone_home' => 'VARCHAR(255)',
			'phone_cell' => 'VARCHAR(255)',
			'email' => 'VARCHAR(255)',
			'ip_address' => 'VARCHAR(255)',
			'dob' => 'DATE',
			'ssn' => 'VARCHAR(255)',
			'track_id' => 'VARCHAR(64)',
			'legal_id_number' => 'VARCHAR(255)',
			'legal_id_state' => 'VARCHAR(5)',
			'bank_name' => 'VARCHAR(255)',
			'bank_aba' => 'VARCHAR(255)',
			'bank_account' => 'VARCHAR(255)'
		);
	}

	protected function getAchFailures($date)
	{
		$args = array();
		
		// Create ach return temporary table
		$query = $this->payment_query_builder->getACHReturnsTemporaryTableQuery($date, $this->config->getCompany(), $args);
		DB_Util_1::queryPrepared($this->config->getConnection(), $query, $args);
		
		// Get application ID's from temporary table
		$results = DB_Util_1::queryPrepared($this->config->getConnection(),
			$this->payment_query_builder->getACHReturnsTempTableApplicationsQuery());
		
		$application_list = array();
		while ($row = $results->fetch(DB_IStatement_1::FETCH_ASSOC))
		{
			$application_list[] = $row['application_id'];
		}
		
		// Store them in the temporary application table
		$this->createApplicationServiceTemporaryTable(
			ECashCra_Driver_Commercial_PaymentQueryBuilder::ACH_RETURN_APPLICATION_TEMP_TABLE, $application_list,
			self::FINANCE_PERCENT);
		
		$query = $this->payment_query_builder->getACHReturnsQuery();

		$rs = DB_Util_1::queryPrepared($this->config->getConnection(), $query, $args);
		return $this->data_builder->getPaymentData($rs);
	}

	protected function getAchPayments($date)
	{
		$args = array();
		
		// Create ach payment temp table
		$query = $this->payment_query_builder->getACHPaymentTempTableQuery($date, $this->config->getCompany(), $args);
		DB_Util_1::queryPrepared($this->config->getConnection(), $query, $args);
		
		// Get application ID's from temp table
		$results = DB_Util_1::queryPrepared($this->config->getConnection(), $this->payment_query_builder->getACHPaymentApplications());
		
		$application_list = array();
		while ($row = $results->fetch(DB_IStatement_1::FETCH_ASSOC))
		{
			$application_list[] = $row['application_id'];
		}
		
		// Store them in the temporary application table
		$this->createApplicationServiceTemporaryTable(
			ECashCra_Driver_Commercial_PaymentQueryBuilder::ACH_PAYMENT_APPLICATION_TEMP_TABLE, $application_list,
			self::FINANCE_PERCENT);

		$rs = DB_Util_1::queryPrepared($this->config->getConnection(),
			$this->payment_query_builder->getACHPaymentsQuery());
		return $this->data_builder->getPaymentData($rs);
	}

	/**
	 * Returns the current balance for the given application.
	 *
	 * @param ECashCra_Data_Application $application
	 * @return float
	 */
	public function getApplicationBalance(ECashCra_Data_Application $application)
	{
		$aux_data = new ECashCra_Driver_Commercial_DataDecorator($application);
		return $aux_data->balance;
	}

	/**
	 * Translates the status of the given application into a valid CRA status.
	 *
	 * @param ECashCra_Data_Application $application
	 * @return string
	 */
	public function translateStatus(ECashCra_Data_Application $application)
	{
		$aux_data = new ECashCra_Driver_Commercial_DataDecorator($application);
		$status_chain = $application->getStatusChain();

		switch ($status_chain)
		{
			case 'sent::external_collections::*root':
			case 'chargeoff::collections::customer::*root':
				return ECashCra_Scripts_UpdateStatuses::STATUS_CHARGEOFF;
				break;

			case 'recovered::external_collections::*root':
				return ECashCra_Scripts_UpdateStatuses::STATUS_FULL_RECOVERY;
				break;

			case 'paid::customer::*root':
				return ECashCra_Scripts_UpdateStatuses::STATUS_CLOSED;
				break;

			default:
				return NULL;
		}
	}

	/**
	 * Returns the amount that was recovered for the given application on the
	 * given date.
	 *
	 * @param ECashCra_Data_Application $application
	 * @param string $date YYYY-MM-DD
	 * @return float
	 */
	public function getRecoveryAmount(ECashCra_Data_Application $application, $date)
	{
		$aux_data = new ECashCra_Driver_Commercial_DataDecorator($application);
		return empty($aux_data->recovery_amount)
			? 0
			: $aux_data->recovery_amount;
	}

	/**
	 * Scripts will pass extra arguments used on the command line to the driver
	 * using this function.
	 *
	 * @param array $arguments
	 * @return null
	 */
	public function handleArguments(array $arguments)
	{
		try {
			$this->config->useArguments($arguments);
		}
		catch (InvalidArgumentException $e)
		{
			die($e->getMessage() . "\n");
		}

		require_once(dirname(__FILE__).'/../../../../www/config.php');
	}

	protected function getUpdatableStatusIds()
	{
		$retval = array();
		$status = ECash::getFactory()->getReferenceList('ApplicationStatusFlat');
		foreach($this->config->getUpdateableStatuses() as $chain)
		{
			try
			{
				$retval[] = $status->toId($chain);
			}
			catch(Exception $e)
			{
				//this means that there was a status that isn't valid for this company
				continue;
			}
		}
		return $retval;
	}
	
	protected function getCancellationStatusIds()
    {
		$status = ECash::getFactory()->getReferenceList('ApplicationStatusFlat');
		foreach($this->config->getCancellationStatuses() as $chain)
		{
			try
			{
				$retval[] = $status->toId($chain);
			}
			catch(Exception $e)
			{
				// this means that there was a status that isn't valid for this company
				continue;
			}
		}
		return $retval;
	}

	protected function getActiveStatusId()
	{
		$status = ECash::getFactory()->getReferenceList('ApplicationStatusFlat');
		return $status->toid($this->config->getActiveStatus());
	}
	
    protected function getWithdrawnStatusId()
	{
		$status = ECash::getFactory()->getReferenceList('ApplicationStatusFlat');
		return $status->toid($this->config->getWithdrawnStatus());
	}
	
	public function setApplicationBalance(ECashCra_Data_Application $application, array $db_row)
	{
		$aux_data = new ECashCra_Driver_Commercial_DataDecorator($application);
		$aux_data->balance = $db_row['balance'];
	}
	
	public function setStatusChain(ECashCra_Data_Application $application, array $db_row)
	{
		$aux_data = new ECashCra_Driver_Commercial_DataDecorator($application);
		$status = ECash::getFactory()->getReferenceList('ApplicationStatusFlat');
		$aux_data->status_chain = $status->toName($db_row['application_status_id']);
	}
	
	public function setRecoveryAmount(ECashCra_Data_Application $application, array $db_row)
	{
		$aux_data = new ECashCra_Driver_Commercial_DataDecorator($application);
		$aux_data->recovery_amount = $db_row['recovery_amount'];
	}

	/**
	 * Returns clarity formated application objects with new loans on the given date
	 *
	 * @param string $date YYYY-MM-DD
	 * @return array
	 */
	public function getClarityNewLoans($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->getClarityNewLoanQuery(
			$date,
			$company,
			$args
		);
//print_r($query."\n");
//print_r($args);
//print_r("\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

        return $results->fetchAll();
	}

    
	/**
	 * Returns the transaction information for a single application
	 *
	 * @param string date YYYY-MM-DD
	 * @param string application_id
	 * @return array
	 */
	public function getClarityApTrans($date, $app_id)
	{
		$args = array();
		$db = $this->config->getConnection();
		
		$query = $this->application_query_builder->getClarityApTransQuery(
			$date,
            $app_id,
			$args
		);
//print_r($query."\n");
//print_r($args);
//print_r("\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

        return $results->fetchAll();
	}
    
	/**
	 * Returns the historical status for a single application
	 *
	 * @param string date YYYY-MM-DD
	 * @param string application_id
	 * @return array
	 */
	public function getClarityApStatus($date, $app_id)
	{
		$args = array();
		$db = $this->config->getConnection();
		
		$query = $this->application_query_builder->getClarityApStatusQuery(
			$date,
            $app_id,
			$args
		);
//print_r($query."\n");
//print_r($args);
//print_r("\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

        return $results->fetchAll();
	}
    
	/**
	 * Returns clarity formated application objects with payments on the given date
	 *
	 * @param string $date YYYY-MM-DD
	 * @return array
	 */
	public function getClarityPayments($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->getClarityPaymentsQuery(
			$date,
			$company,
			$args
		);
//print_r($query."\n");
//print_r($args);
//print_r("\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

        return $results->fetchAll();
	}
    
	/**
	 * Returns clarity formated application objects with payments on the given date
	 *
	 * @param string $date YYYY-MM-DD
	 * @return array
	 */
	public function getClarityPaymentsMissed($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->getClarityPaymentsMissedQuery(
			$date,
			$company,
			$args
		);
//print_r($query."\n");
//print_r($args);
//print_r("\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

        return $results->fetchAll();
	}
    
	/**
	 * Returns clarity formated application objects with payments on the given date
	 *
	 * @param string $date YYYY-MM-DD
	 * @return array
	 */
	public function getClarityPaymentsCaughtUp($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->getClarityPaymentsCaughtUpQuery(
			$date,
			$company,
			$args
		);
//print_r($query."\n");
//print_r($args);
//print_r("\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

        return $results->fetchAll();
	}
    
	/**
	 * Returns clarity formated application objects with payments on the given date
	 *
	 * @param string $date YYYY-MM-DD
	 * @return array
	 */
	public function getClarityPaidInFull($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		// two weeks back
		//$query = $this->application_query_builder->getClarityPaidInFullQuery(14, $date, $company, $args);
		
		$query = $this->application_query_builder->getClarityPaidInFullQuery($date, $company, $args);
//print_r($query."\n");
//print_r($args);
//print_r("\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

        return $results->fetchAll();
	}
    
	/**
	 * Returns clarity formated application objects with payments on the given date
	 *
	 * @param string $date YYYY-MM-DD
	 * @return array
	 */
	public function getClarityVoids($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->getClarityVoidsQuery(
			$date,
			$company,
			$args
		);
//print_r($query."\n");
//print_r($args);
//print_r("\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

        return $results->fetchAll();
	}
    
	/**
	 * Returns clarity formated application objects with payments on the given date
	 *
	 * @param string $date YYYY-MM-DD
	 * @return array
	 */
	public function getClarityWriteOffs($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();
		
		$query = $this->application_query_builder->getClarityWriteOffsQuery(
			$date,
			$company,
			$args
		);
//print_r($query."\n");
//print_r($args);
//print_r("\n");
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

        return $results->fetchAll();
	}

	////////////////////////////////////////////////////////DATAX CLH
	public function getFundUpdateCLH($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();

		$query = $this->application_query_builder->getFundUpdateCLHQuery(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

		return $results->fetchAll();
	}
	
	public function getActiveCLH($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();

		$query = $this->application_query_builder->getActiveCLHQuery(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

		return $results->fetchAll();
	}
	
	public function getCancelCLH($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();

		$query = $this->application_query_builder->getCancelCLHQuery(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

		return $results->fetchAll();
	}
	
	public function getPaidOffCLH($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();

		$query = $this->application_query_builder->getPaidOffCLHQuery(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

		return $results->fetchAll();
	}
	
	public function getChargeOffCLH($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();

		$query = $this->application_query_builder->getChargeOffCLHQuery(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

		return $results->fetchAll();
	}
	
	public function getRecoveryCLH($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();

		$query = $this->application_query_builder->getRecoveryCLHQuery(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

		return $results->fetchAll();
	}
	
	public function getPaymentCLH($date)
	{
		$args = array();
		$db = $this->config->getConnection();
		$company = $this->config->getCompany();

		$query = $this->application_query_builder->getPaymentCLHQuery(
		$date,
		$company,
		$args
		);
		$results = DB_Util_1::queryPrepared($db, $query, $args);
		unset($query);

		return $results->fetchAll();
	}
}
