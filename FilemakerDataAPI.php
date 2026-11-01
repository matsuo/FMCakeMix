<?php
/**
 * FMCakeMix for CakePHP 2 - FileMaker Data API driver
 *
 * This datasource talks to FileMaker Server via the FileMaker Data API by using
 * the FMDataAPI library (https://github.com/msyk/FMDataAPI).
 * The XML API based driver (Filemaker.php) is left untouched; choose the driver
 * with the 'datasource' key in app/Config/database.php.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

// =================================================================================
// = FMDataAPI : required library
// =================================================================================
// FMDataAPI is a class developed by Masayuki Nii to access FileMaker Server
// via the FileMaker Data API. It requires PHP 8.1 or later.
// Web Site: https://github.com/msyk/FMDataAPI
// Place the "src" folder of the repository as app/Vendor/FMDataAPI/src,
// or install it with Composer (inter-mediator/fmdataapi).
// =================================================================================

App::uses('Filemaker', 'Model/Datasource/Database');
App::uses('CakeLog', 'Log');

class FilemakerDataAPI extends Filemaker {

	public $description = "FileMaker Data API Driver";

	public $_baseConfig = array(
		'host' => '127.0.0.1',
		'port' => 443,
		'scheme' => 'https',
		'login' => '',
		'password' => '',
		'database' => '',
		'defaultLayout' => '',
		'certValidating' => true,
		'timeout' => null,
		'maxRecords' => 1000000,
		'vendorPath' => null,
	);

/**
 * Mapping from XML API script parameters to Data API script parameters
 *
 * @var array
 */
	public $scriptParameterMap = array(
		'-script' => 'script',
		'-script.param' => 'script.param',
		'-script.prefind' => 'script.prerequest',
		'-script.prefind.param' => 'script.prerequest.param',
		'-script.presort' => 'script.presort',
		'-script.presort.param' => 'script.presort.param',
	);

/**
 * FMDataAPI instances per FileMaker database name
 *
 * @var array
 */
	protected $_fmInstances = array();

/**
 * Last error (code and message)
 *
 * @var string|null
 */
	protected $_lastErrorMessage = null;

/**
 * Connect. Loads FMDataAPI library. Actual login happens lazily on first request.
 *
 * @return bool
 * @throws MissingConnectionException when FMDataAPI library can not be found
 */
	public function connect() {
		$this->connected = false;
		$this->_loadLibrary();
		$this->connection = null;
		$this->connected = true;
		return $this->connected;
	}

/**
 * Disconnect. Ends Data API sessions.
 *
 * @return bool
 */
	public function disconnect() {
		foreach ($this->_fmInstances as $fm) {
			try {
				$fm->endCommunication();
			} catch (Exception $e) {
				// ignore errors on logout
			}
		}
		$this->_fmInstances = array();
		$this->connected = false;
		return true;
	}

/**
 * Destructor. Ends Data API sessions.
 */
	public function __destruct() {
		$this->disconnect();
		if (method_exists(get_parent_class(__CLASS__), '__destruct')) {
			parent::__destruct();
		}
	}

/**
 * Loads FMDataAPI library files if they are not loaded yet.
 *
 * @return void
 * @throws MissingConnectionException
 */
	protected function _loadLibrary() {
		if (class_exists('INTERMediator\FileMakerServer\RESTAPI\FMDataAPI')) {
			return;
		}

		$candidates = array();
		if (!empty($this->config['vendorPath'])) {
			$candidates[] = rtrim($this->config['vendorPath'], DS) . DS;
		}
		foreach (App::path('Vendor') as $vendorPath) {
			$candidates[] = $vendorPath . 'FMDataAPI' . DS . 'src' . DS;
		}

		$files = array(
			'Supporting' . DS . 'CommunicationProvider.php',
			'Supporting' . DS . 'FileMakerLayout.php',
			'Supporting' . DS . 'FileMakerRelation.php',
			'FMDataAPI.php',
		);

		foreach ($candidates as $dir) {
			if (file_exists($dir . 'FMDataAPI.php')) {
				foreach ($files as $file) {
					if (file_exists($dir . $file)) {
						require_once $dir . $file;
					}
				}
				return;
			}
		}

		throw new MissingConnectionException(array(
			'class' => get_class($this),
			'message' => 'FMDataAPI library is not found. Place it as app/Vendor/FMDataAPI/src.',
			'enabled' => true,
		));
	}

/**
 * Returns FMDataAPI instance for the database (created lazily)
 *
 * @param string $database
 * @return \INTERMediator\FileMakerServer\RESTAPI\FMDataAPI
 */
	protected function _getFM($database) {
		if (!isset($this->_fmInstances[$database])) {
			$fm = $this->_createFM($database);
			$fm->startCommunication();
			$this->_fmInstances[$database] = $fm;
		}
		return $this->_fmInstances[$database];
	}

/**
 * Creates and configures FMDataAPI instance (without logging in)
 *
 * @param string $database
 * @return \INTERMediator\FileMakerServer\RESTAPI\FMDataAPI
 */
	protected function _createFM($database) {
		$config = $this->config;
		$login = (string)$config['login'];
		$password = (string)$config['password'];
		$port = empty($config['port']) ? null : (int)$config['port'];
		$fm = new \INTERMediator\FileMakerServer\RESTAPI\FMDataAPI(
			$database, $login, $password, $config['host'], $port, $config['scheme']
		);
		$fm->setCertValidating((bool)$config['certValidating']);
		if (!empty($config['timeout'])) {
			$fm->setTimeout((int)$config['timeout']);
		}
		$fm->setRetryOnAccessTokenInvalidation(true);
		return $fm;
	}

/**
 * Returns FileMakerLayout object for the model
 *
 * @param Model $model
 * @return \INTERMediator\FileMakerServer\RESTAPI\Supporting\FileMakerLayout
 */
	protected function _getLayout($model) {
		$fm_database = empty($model->fmDatabaseName) ? $this->config['database'] : $model->fmDatabaseName;
		$fm_layout = empty($model->defaultLayout) ? $this->config['defaultLayout'] : $model->defaultLayout;
		return $this->_getFM($fm_database)->layout($fm_layout);
	}

/**
 * The "R" in CRUD
 *
 * @param Model $model A Model object that the query is for.
 * @param array $queryData An array of queryData information containing keys similar to Model::find().
 * @param int $recursive Number of levels of association
 * @return mixed boolean false on error/failure.  An array of results on success.
 */
	public function read(Model $model, $queryData = array(), $recursive = null) {
		// take recursive value from recursive param or from queryData
		$_recursive = $model->recursive;
		if (!is_null($recursive)) {
			$model->recursive = $recursive;
		} else if (isset($queryData['recursive'])) {
			$model->recursive = $queryData['recursive'];
		}

		$resultsOut = $this->_readRecords($model, $queryData, 'read', false);

		if ($resultsOut === false || isset($resultsOut[0][0]['count'])) {
			if (!is_null($recursive)) {
				$model->recursive = $_recursive;
			}
			return $resultsOut;
		}

		// ================================
		// = Searching for Related Models =
		// ================================
		$linkedModels = array();
		if ($model->recursive > 0) {
			if (is_array($model->_associations)) {
				foreach ($model->_associations as $type) {
					foreach ($model->{$type} as $assoc => $assocData) {
						$linkModel = $model->{$assoc};

						if (!in_array($type . '/' . $assoc, $linkedModels)) {
							if ($model->useDbConfig == $linkModel->useDbConfig) {
								$db = $this;
							} else {
								$db = ConnectionManager::getDataSource($linkModel->useDbConfig);
							}
						} elseif ($model->recursive > 1 && ($type == 'belongsTo' || $type == 'hasOne')) {
							$db = $this;
						}

						if (isset($db)) {
							$stack = array($assoc);
							$array = array();
							$db->queryAssociation($model, $linkModel, $type, $assoc, $assocData, $array, true, $resultsOut, $model->recursive - 1, $stack);
							unset($db);
						}
					}
				}
			}
		}

		if (!is_null($recursive)) {
			$model->recursive = $_recursive;
		}

		return $resultsOut;
	}

/**
 * readAssociated
 * very similar to read but for related data
 *
 * @param Model $linkedModel
 * @param array $queryData
 * @param integer $recursive Number of levels of association
 * @return mixed
 */
	public function readAssociated($linkedModel, $queryData = array(), $recursive = null) {
		return $this->_readRecords($linkedModel, $queryData, 'readassociated', true);
	}

/**
 * Performs find request and formats the results
 *
 * @param Model $model
 * @param array $queryData
 * @param string $actionName
 * @param bool $plainFieldByRegex strip model name from field names by regex (for associations)
 * @return mixed false on error, array of results on success
 */
	protected function _readRecords($model, $queryData, $actionName, $plainFieldByRegex) {
		$isCount = (isset($queryData['fields']) && $queryData['fields'] === 'COUNT');
		$conditions = isset($queryData['conditions']) ? $queryData['conditions'] : array();
		$order = isset($queryData['order']) ? $queryData['order'] : array();

		$parsed = $this->_buildQuery($model, $conditions, $plainFieldByRegex);
		$sort = $this->_buildSort($model, $order, $plainFieldByRegex);
		$script = $parsed['script'];
		if (!empty($model->responseLayout)) {
			$script['layout.response'] = $model->responseLayout;
		}
		$script = empty($script) ? null : $script;

		if ($isCount) {
			$limit = 1;
		} else if (!isset($queryData['limit']) || $queryData['limit'] === null || $queryData['limit'] === '') {
			$limit = (int)$this->config['maxRecords'];
		} else {
			$limit = (int)$queryData['limit'];
		}
		$offset = empty($queryData['offset']) ? 0 : (int)$queryData['offset'] + 1;

		$layout = null;
		try {
			$layout = $this->_getLayout($model);
			if (!is_null($parsed['recid'])) {
				$fmResults = $layout->getRecord((int)$parsed['recid'], array(), $script);
			} else {
				$fmResults = $layout->query($parsed['query'], $sort, $offset, $limit, array(), $script);
			}
		} catch (Exception $e) {
			$this->_handleError($e, $layout, $model->name, $actionName . ($isCount ? ' (count)' : ''));
			return false;
		}

		if ($isCount) {
			$count = 0;
			if (!is_null($fmResults)) {
				$count = is_null($parsed['recid']) ? (int)$fmResults->getFoundCount() : 1;
			}
			$this->_logSuccess($layout, $model->name, $actionName . ' (count)', $count);
			return array(array(array('count' => $count)));
		}

		$resultsOut = $this->_formatResults($model->name, $fmResults);
		$this->_logSuccess($layout, $model->name, $actionName, count($resultsOut));
		return $resultsOut;
	}

/**
 * Converts FileMakerRelation object to CakePHP result array
 *
 * @param string $modelName
 * @param \INTERMediator\FileMakerServer\RESTAPI\Supporting\FileMakerRelation|null $fmResults
 * @return array
 */
	protected function _formatResults($modelName, $fmResults) {
		$resultsOut = array();
		if (is_null($fmResults)) {
			return $resultsOut;
		}
		$i = 0;
		foreach ($fmResults as $record) {
			$resultsOut[$i][$modelName]['-recid'] = (string)$record->getRecordId();
			$resultsOut[$i][$modelName]['-modid'] = (string)$record->getModId();
			foreach ($record->toArray() as $field => $value) {
				if (is_array($value)) {
					$value = isset($value[0]) ? $value[0] : null;
				}
				$resultsOut[$i][$modelName][$field] = (is_null($value) || is_string($value)) ? $value : (string)$value;
			}
			$i++;
		}
		return $resultsOut;
	}

/**
 * Builds Data API find request from CakePHP conditions
 *
 * @param Model $model
 * @param mixed $conditions
 * @param bool $plainFieldByRegex
 * @return array with keys 'query' (array|null), 'script' (array), 'recid' (mixed)
 */
	protected function _buildQuery($model, $conditions, $plainFieldByRegex = false) {
		$result = array('query' => null, 'script' => array(), 'recid' => null);

		if (empty($conditions)) {
			return $result;
		}

		// string condition like "field=value" (used by hasOne association)
		if (is_string($conditions)) {
			$parts = explode('=', $conditions, 2);
			if (count($parts) != 2) {
				return $result;
			}
			$conditions = array(trim($parts[0]) => trim($parts[1]));
		}

		$flat = array();
		$isOr = false;
		foreach ($conditions as $conditionField => $conditionValue) {
			if ($conditionField === 'or' || $conditionField === 'OR') {
				$isOr = true;
				if (is_array($conditionValue)) {
					$flat = array_merge($flat, $conditionValue);
				}
			} else {
				$flat[$conditionField] = $conditionValue;
			}
		}

		// collect operators (field.op)
		$operators = array();
		foreach ($flat as $conditionField => $conditionValue) {
			if ($this->_parseField($model, $conditionField, 'operator', $plainFieldByRegex)) {
				$operators[$this->_parseField($model, $conditionField, 'field', $plainFieldByRegex)] = $conditionValue;
				unset($flat[$conditionField]);
			}
		}

		$andRequest = array();
		$orRequests = array();
		$omitRequests = array();
		foreach ($flat as $conditionField => $conditionValue) {
			$field = $this->_parseField($model, $conditionField, 'field', $plainFieldByRegex);

			if (isset($this->scriptParameterMap[$field])) {
				$result['script'][$this->scriptParameterMap[$field]] = $conditionValue;
				continue;
			}
			if (in_array($field, $this->allowed_parameters)) {
				// parameters which have no equivalent in Data API (ex. *.op)
				continue;
			}
			if ($field === '-recid') {
				$result['recid'] = $conditionValue;
				continue;
			}
			if ($field === '-modid') {
				continue;
			}

			$operator = isset($operators[$field]) ? strtolower($operators[$field]) : 'eq';
			if (is_array($conditionValue)) {
				$conditionValue = implode("\r", $conditionValue);
			}
			if ($operator === 'neq') {
				$omitRequests[] = array($field => '=' . $conditionValue, 'omit' => 'true');
				continue;
			}
			$criteria = $this->_findCriteria($conditionValue, $operator);
			if ($isOr) {
				$orRequests[] = array($field => $criteria);
			} else {
				$andRequest[$field] = $criteria;
			}
		}

		$query = $isOr ? $orRequests : (empty($andRequest) ? array() : array($andRequest));
		if (!empty($omitRequests)) {
			if (empty($query)) {
				// omit requests require at least one find request:
				// find all records (non-empty or empty) of the field, then omit
				$field = key($omitRequests[0]);
				$query[] = array($field => '*');
				$query[] = array($field => '=');
			}
			$query = array_merge($query, $omitRequests);
		}
		$result['query'] = empty($query) ? null : $query;

		return $result;
	}

/**
 * Converts XML API style operator to FileMaker find criteria
 *
 * @param string $value
 * @param string $operator
 * @return string
 */
	protected function _findCriteria($value, $operator) {
		$value = (string)$value;
		switch ($operator) {
			case 'cn':
				return '*' . $value . '*';
			case 'bw':
				return $value . '*';
			case 'ew':
				return '*' . $value;
			case 'gt':
				return '>' . $value;
			case 'gte':
				return '>=' . $value;
			case 'lt':
				return '<' . $value;
			case 'lte':
				return '<=' . $value;
			case 'eq':
			default:
				return '=' . $value;
		}
	}

/**
 * Builds Data API sort parameter from CakePHP order
 *
 * @param Model $model
 * @param array $order
 * @param bool $plainFieldByRegex
 * @return array|null
 */
	protected function _buildSort($model, $order, $plainFieldByRegex = false) {
		$sort = array();
		if (empty($order) || !is_array($order)) {
			return null;
		}
		if (version_compare(Configure::version(), '2.8', '>=')) {
			foreach ($order as $conditionField => $sortRule) {
				if (is_int($conditionField) && is_array($sortRule)) {
					foreach ($sortRule as $f => $r) {
						$this->_addSort($sort, $model, $f, $r, $plainFieldByRegex);
					}
					continue;
				}
				$this->_addSort($sort, $model, $conditionField, $sortRule, $plainFieldByRegex);
			}
		} else {
			foreach ($order as $orderCondition) {
				if (!empty($orderCondition) && is_array($orderCondition)) {
					foreach ($orderCondition as $conditionField => $sortRule) {
						$this->_addSort($sort, $model, $conditionField, $sortRule, $plainFieldByRegex);
					}
				}
			}
		}
		return empty($sort) ? null : $sort;
	}

/**
 * Adds a sort rule
 *
 * @param array &$sort
 * @param Model $model
 * @param string $conditionField
 * @param mixed $sortRule
 * @param bool $plainFieldByRegex
 * @return void
 */
	protected function _addSort(&$sort, $model, $conditionField, $sortRule, $plainFieldByRegex) {
		if (is_int($conditionField) || $sortRule === false || $sortRule === null) {
			return;
		}
		$field = $this->_parseField($model, $conditionField, 'field', $plainFieldByRegex);
		if (!empty($field)) {
			$sort[] = array($field, strtolower($sortRule) == 'desc' ? 'descend' : 'ascend');
		}
	}

/**
 * Parses a field string of conditions or order
 *
 * @param Model $model
 * @param string $field
 * @param string $matchPart model|field|operator
 * @param bool $plainFieldByRegex
 * @return string|null
 */
	protected function _parseField($model, $field, $matchPart, $plainFieldByRegex) {
		if (!$plainFieldByRegex) {
			return $this->parseConditionField($model, $field, $matchPart);
		}
		$operator = null;
		if (substr($field, -3) === '.op') {
			$operator = 'op';
			$field = substr($field, 0, -3);
		}
		if ($matchPart === 'operator') {
			return $operator;
		}
		return @preg_replace('/(\w+)\.(-*\w+)$/i', '${2}', $field);
	}

/**
 * Normalizes a value to be stored in FileMaker
 *
 * @param mixed $value
 * @return string
 */
	protected function _normalizeValue($value) {
		if (is_array($value)) {
			return implode("\r", $value);
		}
		if (is_null($value)) {
			return '';
		}
		if (is_bool($value)) {
			return $value ? '1' : '0';
		}
		if (function_exists('mb_ereg_replace')) {
			return mb_ereg_replace("\n", "\r", mb_ereg_replace("\r\n", "\r", (string)$value));
		}
		return str_replace("\n", "\r", str_replace("\r\n", "\r", (string)$value));
	}

/**
 * Splits fields and values into field data and script parameters
 *
 * @param Model $model
 * @param array $fields
 * @param array $values
 * @return array with keys 'data', 'script', 'recid', 'modid'
 */
	protected function _splitFieldData($model, $fields, $values) {
		$result = array('data' => array(), 'script' => array(), 'recid' => null, 'modid' => null);
		foreach ($fields as $index => $field) {
			$value = $values[$index];
			if ($field === '-recid') {
				$result['recid'] = $value;
			} else if ($field === '-modid') {
				$result['modid'] = $value;
			} else if (isset($this->scriptParameterMap[$field])) {
				$result['script'][$this->scriptParameterMap[$field]] = $value;
			} else if (in_array($field, $this->allowed_parameters)) {
				continue;
			} else {
				$result['data'][$field] = $this->_normalizeValue($value);
			}
		}
		return $result;
	}

/**
 * The "C" in CRUD
 *
 * @param Model $model Model object that the record is for.
 * @param array $fields An array of field names to insert.
 * @param array $values An array of values with keys matching the fields.
 * @return boolean Success
 */
	public function create(Model $model, $fields = null, $values = null) {
		if ($fields == null) {
			unset($fields, $values);
			$fields = array_keys($model->data);
			$values = array_values($model->data);
		}
		$fields = array_values($fields);
		$values = array_values($values);

		// if by chance the recid was passed to this create method we want
		// to make sure we remove it as filemaker will reject the request.
		if (isset($model->fm_recid) && !empty($model->fm_recid)) {
			foreach ($fields as $index => $field) {
				if ($field == $model->fm_recid) {
					unset($fields[$index]);
					unset($values[$index]);
				}
			}
		}

		$split = $this->_splitFieldData($model, $fields, $values);
		$script = empty($split['script']) ? null : $split['script'];

		// FileMaker rejects a new record request with -recid (same as XML API)
		if (!$this->_isBlank($split['recid'])) {
			$this->_handleError(new Exception('-recid can not be specified on create', 101), null, $model->name, 'new');
			return false;
		}

		$layout = null;
		try {
			$layout = $this->_getLayout($model);
			$recordId = $layout->create($split['data'], null, $script);
			$fmResults = $layout->getRecord((int)$recordId);
		} catch (Exception $e) {
			$this->_handleError($e, $layout, $model->name, 'new');
			return false;
		}
		$this->_logSuccess($layout, $model->name, 'new', 1);

		$resultsOut = array();
		$formatted = $this->_formatResults($model->name, $fmResults);
		if (!empty($formatted)) {
			$resultsOut = $formatted[0];
			if (isset($resultsOut[$model->name][$model->primaryKey])) {
				// write primary key to model id and __lastinsert attributes
				$model->id = $resultsOut[$model->name][$model->primaryKey];
				$model->setInsertID($resultsOut[$model->name][$model->primaryKey]);
			}
		}

		$model->data = $resultsOut; // this returns data on a create

		return true;
	}

/**
 * The "U" in CRUD
 * It's important to note that edit requires a FileMaker -recid.
 *
 * @param Model $model
 * @param array $fields
 * @param array $values
 * @param mixed $conditions
 * @return boolean Success
 */
	public function update(Model $model, $fields = array(), $values = null, $conditions = null) {
		$fields = array_values((array)$fields);
		$values = array_values((array)$values);

		// remove the primary key field if it's marked as readonly
		foreach ($fields as $index => $field) {
			if (isset($model->primaryKeyReadOnly) && $field == $model->primaryKey) {
				unset($fields[$index]);
				unset($values[$index]);
			} else if ($field == $model->primaryKey) {
				$model->id = $values[$index];
				$model->setInsertID($values[$index]);
			}
		}

		$split = $this->_splitFieldData($model, $fields, $values);
		$recid = $split['recid'];
		if ($this->_isBlank($recid) && is_array($conditions)) {
			$recid = $this->_recidFromConditions($model, $conditions);
		}
		if ($this->_isBlank($recid)) {
			$recid = $model->field('-recid');
		}
		if (!$this->_isValidRecid($recid)) {
			$this->_handleError(new Exception('Invalid -recid: ' . var_export($recid, true), 101), null, $model->name, 'update');
			return false;
		}

		$modId = (isset($split['modid']) && is_numeric($split['modid'])) ? (int)$split['modid'] : -1;
		$script = empty($split['script']) ? null : $split['script'];

		$layout = null;
		try {
			$layout = $this->_getLayout($model);
			$layout->update((int)$recid, $split['data'], $modId, null, $script);
		} catch (Exception $e) {
			$this->_handleError($e, $layout, $model->name, 'update');
			return false;
		}
		$this->_logSuccess($layout, $model->name, 'update', 1);
		return true;
	}

/**
 * The "D" in CRUD
 * can only delete from the recid that is internal to filemaker
 *
 * @param Model $model
 * @param array $conditions
 * @return boolean Success
 */
	public function delete(Model $model, $conditions = null) {
		$recid = is_array($conditions) ? $this->_recidFromConditions($model, $conditions) : null;
		if ($this->_isBlank($recid)) {
			$recid = $model->field('-recid');
		}
		if (!$this->_isValidRecid($recid)) {
			$this->_handleError(new Exception('Invalid -recid: ' . var_export($recid, true), 101), null, $model->name, 'delete');
			return false;
		}

		$layout = null;
		try {
			$layout = $this->_getLayout($model);
			$layout->delete((int)$recid);
		} catch (Exception $e) {
			$this->_handleError($e, $layout, $model->name, 'delete');
			return false;
		}
		$this->_logSuccess($layout, $model->name, 'delete', 1);
		return true;
	}

/**
 * Returns -recid value from conditions
 *
 * @param Model $model
 * @param array $conditions
 * @return mixed
 */
	protected function _recidFromConditions($model, $conditions) {
		foreach ($conditions as $field => $value) {
			if (is_string($field) && $this->parseConditionField($model, $field, 'field') === '-recid') {
				return $value;
			}
		}
		return null;
	}

/**
 * Whether the value is null, false or an empty string
 *
 * @param mixed $value
 * @return bool
 */
	protected function _isBlank($value) {
		return $value === null || $value === false || $value === '';
	}

/**
 * Whether the value is a valid FileMaker record id
 *
 * @param mixed $value
 * @return bool
 */
	protected function _isValidRecid($value) {
		return is_numeric($value) && (int)$value > 0;
	}

/**
 * Returns an array of the fields in given table name.
 *
 * @param Model $model the model to inspect
 * @return array Fields in table. Keys are name and type
 */
	public function describe($model) {
		$cache = $this->__describeFromCache($model);
		if ($cache != null) {
			return $cache;
		}

		$layout = null;
		try {
			$layout = $this->_getLayout($model);
			$metadata = $layout->getMetadata();
		} catch (Exception $e) {
			$this->_handleError($e, $layout, $model->name, 'describe');
			return false;
		}
		$this->_logSuccess($layout, $model->name, 'describe', 0);

		$fmFieldTypeConversion = array(
			'text' => 'string',
			'date' => 'date',
			'time' => 'time',
			'timestamp' => 'timestamp',
			'number' => 'float',
			'container' => 'binary'
		);

		$fieldsOut = array();
		$fieldMetaData = (is_object($metadata) && isset($metadata->fieldMetaData)) ? $metadata->fieldMetaData : array();
		foreach ($fieldMetaData as $field) {
			if (empty($field->name)) {
				continue;
			}
			$result = isset($field->result) ? strtolower($field->result) : 'text';
			$fieldsOut[$field->name] = array(
				'type' => isset($fmFieldTypeConversion[$result]) ? $fmFieldTypeConversion[$result] : 'string',
				'null' => null,
				'default' => null,
				'length' => null,
				'key' => null
			);
		}

		foreach (array('-recid', '-modid') as $special) {
			$fieldsOut[$special] = array(
				'type' => 'integer',
				'null' => null,
				'default' => null,
				'length' => null,
				'key' => null
			);
		}

		// add in fm functional parameters so that they don't get cleaned from saves
		foreach ($this->allowed_parameters as $param) {
			$fieldsOut[$param] = array(
				'type' => 'FM_PARAM',
				'null' => null,
				'default' => null,
				'length' => null,
				'key' => null
			);
		}

		// value list handling
		if (!empty($model->returnValueLists) && $model->returnValueLists === true) {
			$valueLists = $this->_parseValueLists($metadata);
			foreach ($fieldMetaData as $field) {
				if (!empty($field->valueList) && isset($valueLists[$field->valueList]) && isset($fieldsOut[$field->name])) {
					$fieldsOut[$field->name]['valuelist'] = $valueLists[$field->valueList];
				}
			}
		}

		$this->__cacheDescription($this->fullTableName($model, false), $fieldsOut);
		return $fieldsOut;
	}

/**
 * Returns value lists of the layout (equivalent of FX::FMView()['valueLists'])
 *
 * Example:
 *   $db = ConnectionManager::getDataSource($model->useDbConfig);
 *   $valueLists = $db->getValueLists('LayoutName');
 *   // array('ValueListName' => array('value1', 'value2', ...), ...)
 *
 * @param Model|string $layout Model object or layout name
 * @param string|null $database FileMaker database name (layout name only).
 *   If omitted, 'database' of the connection config is used.
 * @return array value lists keyed by value list name. Empty array on error.
 */
	public function getValueLists($layout, $database = null) {
		$modelName = is_object($layout) ? $layout->name : 'N/A';
		$layoutObject = null;
		try {
			if (is_object($layout)) {
				$layoutObject = $this->_getLayout($layout);
			} else {
				$fm_database = empty($database) ? $this->config['database'] : $database;
				$layoutObject = $this->_getFM($fm_database)->layout((string)$layout);
			}
			$metadata = $layoutObject->getMetadata();
		} catch (Exception $e) {
			$this->_handleError($e, $layoutObject, $modelName, 'getValueLists');
			return array();
		}
		$valueLists = $this->_parseValueLists($metadata);
		$this->_logSuccess($layoutObject, $modelName, 'getValueLists', count($valueLists));
		return $valueLists;
	}

/**
 * Converts value lists in Data API layout metadata to array
 *
 * @param object|null $metadata response of FileMakerLayout::getMetadata()
 * @return array value lists keyed by value list name
 */
	protected function _parseValueLists($metadata) {
		$valueLists = array();
		if (is_object($metadata) && isset($metadata->valueLists)) {
			foreach ($metadata->valueLists as $valueList) {
				if (!isset($valueList->name)) {
					continue;
				}
				$values = array();
				if (isset($valueList->values)) {
					foreach ($valueList->values as $item) {
						$values[] = isset($item->value) ? $item->value : (isset($item->displayValue) ? $item->displayValue : null);
					}
				}
				$valueLists[$valueList->name] = $values;
			}
		}
		return $valueLists;
	}

/**
 * Returns a formatted error message from previous database operation.
 *
 * @param PDOStatement $query not used
 * @return string|null Error message with error number
 */
	public function lastError(?PDOStatement $query = null) {
		return $this->_lastErrorMessage;
	}

/**
 * Logs an error of Data API request
 *
 * @param Exception $e
 * @param mixed $layout FileMakerLayout object or null
 * @param string $modelName
 * @param string $actionName
 * @return void
 */
	protected function _handleError($e, $layout, $modelName = 'N/A', $actionName = 'N/A') {
		$this->_queriesCnt++;
		$query = $this->_debugInfo($layout);
		$this->_lastErrorMessage = $e->getCode() . ': ' . $e->getMessage();

		$this->_queriesLog[] = array(
			'model' => $modelName,
			'action' => $actionName,
			'query' => $query,
			'error' => $this->_lastErrorMessage,
			'affected' => '',
			'numRows' => '',
			'took' => round((microtime(true) - $this->timeFlag) * 1000, 0)
		);
		if (count($this->_queriesLog) > $this->_queriesLogMax) {
			array_shift($this->_queriesLog);
		}

		CakeLog::write('error', $this->formatErrorMessage('Data API Error', $this->_lastErrorMessage, $modelName, $actionName, $query));

		$this->timeFlag = microtime(true);
	}

/**
 * Logs a successful Data API request
 *
 * @param mixed $layout FileMakerLayout object or null
 * @param string $modelName
 * @param string $actionName
 * @param int $numRows
 * @return void
 */
	protected function _logSuccess($layout, $modelName, $actionName, $numRows) {
		$this->_queriesCnt++;
		$this->_lastErrorMessage = null;
		$this->_queriesLog[] = array(
			'model' => $modelName,
			'action' => $actionName,
			'query' => $this->_debugInfo($layout),
			'error' => 0,
			'affected' => '',
			'numRows' => $numRows,
			'took' => round((microtime(true) - $this->timeFlag) * 1000, 0)
		);
		if (count($this->_queriesLog) > $this->_queriesLogMax) {
			array_shift($this->_queriesLog);
		}
		$this->timeFlag = microtime(true);
	}

/**
 * Returns the request URL and body of the last request
 *
 * @param mixed $layout
 * @return string
 */
	protected function _debugInfo($layout) {
		if (is_null($layout)) {
			return '';
		}
		try {
			return (string)$layout->getDebugInfo();
		} catch (Throwable $t) {
			return '';
		}
	}
}
