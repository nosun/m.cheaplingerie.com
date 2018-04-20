<?php
class Product_Model extends Bl_Model
{
	const FIELD_TYPE_TEXT = 0;
	const FIELD_TYPE_INTEGER = 1;
	const FIELD_TYPE_DATETIME = 2;
	const FIELD_TYPE_FILE = 3;

	const DISPLAY_TYPE_TEXT = 0;
	const DISPLAY_TYPE_SELECT = 1;
	const DISPLAY_TYPE_CHECKBOX = 2;
	const DISPLAY_TYPE_TEXTAREA = 3;
	const DISPLAY_TYPE_RTE = 4;
	const DISPLAY_TYPE_CALENDAR = 5;
	const DISPLAY_TYPE_FILE = 6;

	const STATUS_UNPUBLISHED = 0;
	const STATUS_PUBLISHED = 1;

	public $fieldType = array(
	self::FIELD_TYPE_TEXT => 'Text',
	self::FIELD_TYPE_INTEGER => 'Numeric',
	self::FIELD_TYPE_DATETIME => 'Date',
	self::FIELD_TYPE_FILE => 'File',
	);

	public $displayType = array(
	self::DISPLAY_TYPE_TEXT => 'Text Field',
	self::DISPLAY_TYPE_SELECT => 'Dropdown List',
	self::DISPLAY_TYPE_CHECKBOX => 'Radio / Checkbox',
	self::DISPLAY_TYPE_TEXTAREA => 'Textarea',
	self::DISPLAY_TYPE_RTE => 'Rich Text Editor',
	self::DISPLAY_TYPE_CALENDAR => 'Calendar',
	self::DISPLAY_TYPE_FILE => 'File Uploader',
	);

	public $fieldDisplayType = array(
	self::FIELD_TYPE_TEXT => array(
	self::DISPLAY_TYPE_TEXT,
	self::DISPLAY_TYPE_SELECT,
	self::DISPLAY_TYPE_CHECKBOX,
	self::DISPLAY_TYPE_TEXTAREA,
	self::DISPLAY_TYPE_RTE,
	),
	self::FIELD_TYPE_INTEGER => array(
	self::DISPLAY_TYPE_TEXT,
	self::DISPLAY_TYPE_SELECT,
	self::DISPLAY_TYPE_CHECKBOX,
	),
	self::FIELD_TYPE_DATETIME => array(
	self::DISPLAY_TYPE_TEXT,
	self::DISPLAY_TYPE_CALENDAR,
	),
	self::FIELD_TYPE_FILE => array(
	self::DISPLAY_TYPE_FILE,
	),
	);

	/**
	 * @return Product_Model
	 */
	public static function getInstance()
	{
		return parent::getInstance(__CLASS__);
	}

	/**
	 * 检查类型标识是否有效
	 * @param string $type 商品类型标识
	 * @return boolean
	 */
	public function checkTypeIsValid($type)
	{
		return (boolean)preg_match('/^[a-z][a-z0-9]{2,31}$/i', $type);
	}

	/**
	 * 获取商品类型列表
	 * @return array
	 */
	public function getTypeList()
	{
		global $db;
		static $list;
		if (!isset($list)) {
			$cacheId = 'product-type';
			if ($cache = cache::get($cacheId)) {
				$list = $cache->data;
			} else {
				$result = $db->query('SELECT pt.*, COUNT(p.pid) productsCount FROM products_type pt
          LEFT JOIN products p ON pt.type = p.type GROUP BY pt.type');
				$list = $result->allWithKey('type');
				cache::save($cacheId, $list);
			}
		}
		return $list;
	}

	/**
	 * 获取商品类型信息
	 * @param string $type 商品类型标识
	 * @return object
	 */
	public function getTypeInfo($type)
	{
		$list = $this->getTypeList();
		return isset($list[$type]) ? $list[$type] : false;
	}

	/**
	 * 检查分类是否存在
	 * @param string $type 分类标识
	 * @return boolean
	 */
	public function checkTypeExist($type)
	{
		$types = $this->getTypeList();
		return isset($types[$type]);
	}

	/**
	 * 新增商品类型
	 * @param array $set 商品类型数组
	 * @return boolean
	 */
	public function insertType($post)
	{
		global $db;
		if (!isset($post['type'])) {
			return false;
		} else if (!isset($post['name'])) {
			return false;
		}
		$type = $post['type'];
		$db->insert('products_type', $post);
		cache::remove('product-type');
		$affected = (boolean)$db->affected();
		if ($affected) {
			$this->createTypeTable($type);
		}
		return $affected;
	}

	/**
	 * 修改商品类型
	 * @param string $type 商品类型标识
	 * @param array $set 商品类型数组
	 * @return boolean
	 */
	public function updateType($type, $post)
	{
		global $db;
		if (!isset($post['name'])) {
			return false;
		}
		if (isset($post['type'])) {
			unset($post['type']);
		}
		$db->update('products_type', $post, array('type' => $type));
		cache::remove('product-type');
		return (boolean)$db->affected();
	}

	/**
	 * 删除商品类型
	 * @param string $type 商品类型标识
	 * @return boolean
	 */
	public function deleteType($type)
	{
		global $db;
		$db->delete('products_type', array('type' => $type));
		cache::remove('product-type');
		$affected = (boolean)$db->affected();
		if ($affected) {
			$fields = $this->getTypeFieldsList($type);
			foreach ($fields as $fieldName => $field) {
				$this->deleteTypeField($type, $fieldName);
			}
			$this->dropTypeTable($type);
		}
		return $affected;
	}

	/**
	 * 获取类型扩展表名称
	 * @param string $type
	 * @return string
	 */
	private function getTypeTableName($type)
	{
		return strtolower('type_' . $type);
	}

	/**
	 * 创建商品类型表
	 * @param string $type 商品类型标识
	 */
	private function createTypeTable($type)
	{
		global $db;
		$db->exec('CREATE TABLE IF NOT EXISTS `' . $db->escape($this->getTypeTableName($type)) . '` (
      `pid` INT UNSIGNED NOT NULL,
      PRIMARY KEY (`pid`))
      DEFAULT CHARACTER SET = utf8
      COLLATE = utf8_general_ci');
	}

	/**
	 * 删除商品类型表
	 * @param string $type 商品类型标识
	 */
	private function dropTypeTable($type)
	{
		global $db;
		$db->exec('DROP TABLE IF EXISTS `' . $db->escape($this->getTypeTableName($type)) . '`');
	}

	/**
	 * 检查属性标识是否有效
	 * @param string $type 商品属性标识
	 * @return boolean
	 */
	public function checkFieldNameIsValid($fieldName)
	{
		return (boolean)preg_match('/^[a-z][a-z0-9]{2,31}$/i', $fieldName);
	}

	/**
	 * 获取商品类型字段数量
	 * @param string $type
	 * @return int
	 */
	public function getTypeFieldsCount($type)
	{
		global $db;
		static $list = array();
		if (!isset($list[$type])) {
			$result = $db->query('SELECT COUNT(0) FROM products_type_fields WHERE type = "' . $db->escape($type) . '"');
			$list[$type] = $result->one();
		}
		return $list[$type];
	}

	/**
	 * 获取商品类型字段列表
	 * @param string $type 类型标识
	 * @return array
	 */
	public function getTypeFieldsList($type)
	{
		global $db;
		static $list = array();
		if (!isset($list[$type])) {
			$cacheId = 'product-type-fields-' . $type;
			if ($cache = cache::get($cacheId)) {
				$rows = $cache->data;
			} else {
				$result = $db->query('SELECT * FROM products_type_fields WHERE type = "' . $db->escape($type) . '" ORDER BY weight DESC');
				$rows = $result->allWithKey('field_name');
				foreach ($rows as $fieldName => &$row) {
					if ($row->settings) {
						$settings = unserialize($row->settings);
						foreach ($settings as $key => $value) {
							$row->{$key} = $value;
						}
					}
				}
				cache::save($cacheId, $rows);
			}
			$list[$type] = $rows;
		}
		return $list[$type];
	}

	/**
	 * 获取商品类型字段信息
	 * @param string $type 类型标识
	 * @param string $field 字段标识
	 * @return array
	 */
	public function getTypeFieldInfo($type, $fieldName)
	{
		global $db;
		static $list = array();
		if (!isset($list[$type . '_' . $fieldName])) {
			$result = $db->query('SELECT * FROM products_type_fields WHERE type = "' . $db->escape($type) .
        '" AND field_name = "' . $db->escape($fieldName) . '"');
			$row = $result->row();
			if ($row && $row->settings) {
				$settings = unserialize($row->settings);
				foreach ($settings as $key => $value) {
					$row->{$key} = $value;
				}
			}
			$list[$type . '_' . $fieldName] = $row;
		}
		return $list[$type . '_' . $fieldName];
	}

	/**
	 * 返回输入类型
	 * @param object $field 字段信息
	 * @param mixed $defaultValue 默认值
	 */
	public function getTypeFieldWidget($field, $defaultValue = null, $defaultPrice = 0)
	{
		$fieldName = 'field_' . $field->field_name;
		$multiple = $field->multiple;
		$defaultValue = isset($defaultValue) ? $defaultValue : $field->default_value;
		$options = preg_split('/\r?\n/', $field->options, null, PREG_SPLIT_NO_EMPTY);
		$html = '';
		if ($multiple) {
			if (!is_array($defaultValue)) {
				$defaultValue = preg_split('/\r?\n/', $defaultValue, null, PREG_SPLIT_NO_EMPTY);
			}
			if (empty($defaultValue)) {
				$defaultValue[] = '';
			}
			if ($field->valued && !is_array($defaultPrice)) {
				$defaultPrice = array(0);
			}
		}
		switch ($field->display_type) {
			case self::DISPLAY_TYPE_TEXT:
				if ($multiple) {
					foreach ($defaultValue as $delta => $value) {
						$html .= '<p><input type="text" name="' . plain($fieldName) . '[]" value="' . plain($value) . '">' . PHP_EOL;
						if ($field->valued) {
							$html .= ' ' . t('Value') . ' <input type="text" name="' . plain($fieldName) . '_value[]" size="5" value="' . plain(isset($defaultPrice[$delta]) ? $defaultPrice[$delta] : 0) . '">' . PHP_EOL;
						}
						$html .= ' <a href="javscript:void(0)" class="btn_field_remove">[-]</a></p>' . PHP_EOL;
					}
				} else {
					$html .= '<input type="text" name="' . plain($fieldName) . '" value="' . plain($defaultValue) . '">' . PHP_EOL;
					if ($field->valued) {
						$html .= ' ' . t('Value') . ' <input type="text" name="' . plain($fieldName) . '_value" value="' . plain($defaultPrice) . '">' . PHP_EOL;
					}
				}
				break;
			case self::DISPLAY_TYPE_SELECT:
				if ($multiple) {
					foreach ($defaultValue as $delta => $value) {
						$html .= '<p><select name="' . plain($fieldName) . '[]">' . PHP_EOL;
						if (is_array($options) && !empty($options)) {
							foreach ($options as $option) {
								$html .= '<option value="' . plain($option) . '"' . ($option == $value ? ' selected="selected"' : '') . '>' . plain($option) . '</option>' . PHP_EOL;
							}
						} else {
							$html .= '<option value=""></option>' . PHP_EOL;
						}
						$html .= '</select>' . PHP_EOL;
						if ($field->valued) {
							$html .= ' ' . t('Value') . ' <input type="text" name="' . plain($fieldName) . '_value[]" size="5" value="' . plain(isset($defaultPrice[$delta]) ? $defaultPrice[$delta] : 0) . '">' . PHP_EOL;
						}
						$html .= ' <a href="javscript:void(0)" class="btn_field_remove">[-]</a></p>' . PHP_EOL;
					}
				} else {
					$html .= '<select name="' . plain($fieldName) . '">' . PHP_EOL;
					if (is_array($options) && !empty($options)) {
						foreach ($options as $option) {
							$html .= '<option value="' . plain($option) . '"' . ($option == $defaultValue ? ' selected="selected"' : '') . '>' . plain($option) . '</option>' . PHP_EOL;
						}
					} else {
						$html .= '<option value=""></option>' . PHP_EOL;
					}
					$html .= '</select>' . PHP_EOL;
					if ($field->valued) {
						$html .= ' ' . t('Value') . ' <input type="text" name="' . plain($fieldName) . '_value" value="' . plain($defaultPrice) . '">' . PHP_EOL;
					}
				}
				break;
			case self::DISPLAY_TYPE_CHECKBOX:
				if ($multiple) {
					foreach ($options as $option) {
						$html .= '<p><label><input name="' . plain($fieldName) . '[]" type="checkbox" value="' . plain($option) . '"' . (in_array($option, $defaultValue) ? ' checked="checked"' : '') . '> ' . plain($option) . '</label></p>' . PHP_EOL;
					}
				} else {
					foreach ($options as $option) {
						$html .= '<p><label><input name="' . plain($fieldName) . '" type="radio" value="' . plain($option) . '"' . ($option == $defaultValue ? ' checked="checked"' : '') . '> ' . plain($option) . '</label></p>' . PHP_EOL;
					}
				}
				break;
			case self::DISPLAY_TYPE_TEXTAREA:
				if ($multiple) {
					foreach ($defaultValue as $delta => $value) {
						$html .= '<p><textarea name="' . plain($fieldName) . '[]">' . plain($value) . '</textarea>' . PHP_EOL;
						if ($field->valued) {
							$html .= ' ' . t('Value') . ' <input type="text" name="' . plain($fieldName) . '_value[]" size="5" value="' . plain(isset($defaultPrice[$delta]) ? $defaultPrice[$delta] : 0) . '">' . PHP_EOL;
						}
						$html .= ' <a href="javscript:void(0)" class="btn_field_remove">[-]</a></p>' . PHP_EOL;
					}
				} else {
					$html .= '<textarea name="' . plain($fieldName) . '">' . plain($defaultValue) . '</textarea>' . PHP_EOL;
					if ($field->valued) {
						$html .= ' ' . t('Value') . ' <input type="text" name="' . plain($fieldName) . '_value" value="' . plain($defaultPrice) . '">' . PHP_EOL;
					}
				}
				break;
			case self::DISPLAY_TYPE_RTE:
				if ($multiple) {
					foreach ($defaultValue as $delta => $value) {
						$html .= '<p><textarea name="' . plain($fieldName) . '[]" class="field_xheditor">' . plain($value) . '</textarea>' . PHP_EOL;
						if ($field->valued) {
							$html .= ' ' . t('Value') . ' <input type="text" name="' . plain($fieldName) . '_value[]" size="5" value="' . plain(isset($defaultPrice[$delta]) ? $defaultPrice[$delta] : 0) . '">' . PHP_EOL;
						}
						$html .= ' <a href="javscript:void(0)" class="btn_field_remove">[-]</a></p>' . PHP_EOL;
					}
				} else {
					$html .= '<textarea name="' . plain($fieldName) . '" class="field_xheditor">' . plain($defaultValue) . '</textarea>' . PHP_EOL;
					if ($field->valued) {
						$html .= ' ' . t('Value') . ' <input type="text" name="' . plain($fieldName) . '_value" value="' . plain($defaultPrice) . '">' . PHP_EOL;
					}
				}
				break;
				break;
			case self::DISPLAY_TYPE_CALENDAR:
				if ($multiple) {
					foreach ($defaultValue as $delta => $value) {
						$html .= '<p><input type="text" name="' . plain($fieldName) . '[]" class="field_datepicker" value="' . plain($value) . '">' . PHP_EOL;
						if ($field->valued) {
							$html .= ' ' . t('Value') . ' <input type="text" name="' . plain($fieldName) . '_value[]" size="5" value="' . plain(isset($defaultPrice[$delta]) ? $defaultPrice[$delta] : 0) . '">' . PHP_EOL;
						}
						$html .= ' <a href="javscript:void(0)" class="btn_field_remove">[-]</a></p>';
					}
				} else {
					$html .= '<input type="text" name="' . plain($fieldName) . '" class="field_datepicker" value="' . plain($defaultValue) . '">';
					if ($field->valued) {
						$html .= ' ' . t('Value') . ' <input type="text" name="' . plain($fieldName) . '_value" value="' . plain($defaultPrice) . '">' . PHP_EOL;
					}
				}
				break;
			case self::DISPLAY_TYPE_FILE:
				if ($multiple) {
					//          foreach ($defaultValue as $delta => $value) {
					//            $html .= '<p><input type="file" name="' . plain($fieldName) . '[]" value="' . plain($value) . '">' . PHP_EOL;
					//            if ($field->valued) {
					//              $html .= ' ' . t('Value') . ' <input type="text" name="' . plain($fieldName) . '_value[]" size="5" value="' . plain(isset($defaultPrice[$delta]) ? $defaultPrice[$delta] : 0) . '">' . PHP_EOL;
					//            }
					//            $html .= ' <a href="javscript:void(0)" class="btn_field_remove">[-]</a></p>' . PHP_EOL;
					//          }
				} else {
					$html .= ($defaultValue ? '<img src="' . urlimg('admin_product_album', $defaultValue->filepath) . '">' : '') . '<input type="file" name="' . plain($fieldName) . '">' . PHP_EOL;
					if ($field->valued) {
						$html .= ' ' . t('Value') . ' <input type="text" name="' . plain($fieldName) . '_value" value="' . plain($defaultPrice) . '">' . PHP_EOL;
					}
				}
				break;
		}
		return $html;
	}

	/**
	 * 检查字段是否存在多输入框属性
	 * @param object $field 字段信息
	 */
	public function getTypeFieldHasMultipleInput($field)
	{
		return $field->multiple && in_array($field->display_type, array(self::DISPLAY_TYPE_TEXT, self::DISPLAY_TYPE_SELECT,
		self::DISPLAY_TYPE_TEXTAREA, self::DISPLAY_TYPE_CALENDAR, self::DISPLAY_TYPE_RTE, self::DISPLAY_TYPE_FILE));
	}

	/**
	 * 获取商品类型属性字段默认输入方式
	 * @param string $fieldType 字段类型
	 * @return int
	 */
	public function getTypeFieldDefaultDisplayType($fieldType)
	{
		switch ($fieldType) {
			case self::FIELD_TYPE_TEXT:
			case self::FIELD_TYPE_INTEGER:
				return self::DISPLAY_TYPE_TEXT;
			case self::FIELD_TYPE_DATETIME:
				return self::DISPLAY_TYPE_CALENDAR;
			case self::FIELD_TYPE_FILE:
				return self::DISPLAY_TYPE_FILE;
			default:
				return self::DISPLAY_TYPE_TEXT;
		}
	}

	/**
	 * 新增商品类型字段
	 * @param string $type 商品类型标识
	 * @param array $post 商品属性数组
	 */
	public function insertTypeField($type, $post)
	{
		global $db;
		if (!$this->getTypeInfo($type)) {
			return false;
		} else if (!isset($post['field_name'])) {
			return false;
		} else if (!isset($post['name'])) {
			return false;
		} else if (!isset($post['field_type'])) {
			return false;
		}
		$set = array(
      'field_name' => strtolower($post['field_name']),
      'type' => $type,
      'name' => $post['name'],
      'field_type' => $post['field_type'],
      'field_size' => $post['field_type'] == Product_Model::FIELD_TYPE_TEXT ? 200 : 0,
      'display_type' => $this->getTypeFieldDefaultDisplayType($post['field_type']),
      'required' => (isset($post['required']) && $post['required']) ? 1 : 0,
      'multiple' => (isset($post['multiple']) && $post['multiple']) ? 1 : 0,
      'indexed' => (isset($post['indexed']) && $post['indexed']) ? 1 : 0,
      'valued' => (isset($post['valued']) && $post['valued']) ? 1 : 0,
      'settings' => serialize(isset($post['settings']) ? $post['settings'] : array()),
      'weight' => isset($post['weight']) ? $post['weight'] : 0,
		);
		$db->insert('products_type_fields', $set);
		cache::remove('product-type-fields-' . $type);
		$affected = (boolean)$db->affected();
		if ($affected) {
			$this->createTypeField($type, $set);
		}
		return $affected;
	}

	/**
	 * 修改商品类型字段
	 * @param string $type 商品类型标识
	 * @param string $fieldName 属性类型标识
	 * @param array $post 商品属性数组
	 */
	public function updateTypeField($type, $fieldName, $post)
	{
		global $db;
		if (!$this->getTypeInfo($type)) {
			return false;
		} else if (!$field = $this->getTypeFieldInfo($type, $fieldName)) {
			return false;
		} else if (!isset($post['name'])) {
			return false;
		}
		$set = array(
      'name' => $post['name'],
      'required' => (isset($post['required']) && $post['required']) ? 1 : 0,
      'indexed' => (isset($post['indexed']) && $post['indexed']) ? 1 : 0,
      'settings' => serialize(isset($post['settings']) ? $post['settings'] : array()),
      'weight' => isset($post['weight']) ? $post['weight'] : 0,
		);
		if (isset($post['field_size']) && $field->field_type == self::FIELD_TYPE_TEXT && intval($post['field_size']) != $field->field_size) {
			$set['field_size'] = intval($post['field_size']);
			$this->modifyTypeField($type, array(
        'field_name' => $fieldName,
        'field_type' => $field->field_type,
        'field_size' => $set['field_size'],
        'multiple' => $field->multiple,
        'org_indexed' => $field->indexed,
        'indexed' => $set['indexed'],
			));
		} else if ($set['indexed'] != $field->indexed) {
			$this->modifyTypeField($type, array(
        'field_name' => $fieldName,
        'field_type' => $field->field_type,
        'field_size' => $field->field_size,
        'multiple' => $field->multiple,
        'org_indexed' => $field->indexed,
        'indexed' => $set['indexed'],
			));
		}
		if (isset($post['display_type'])) {
			if (in_array($post['display_type'], $this->fieldDisplayType[$field->field_type])) {
				$set['display_type'] = $post['display_type'];
			} else {
				$set['display_type'] = $this->getTypeFieldDefaultDisplayType($field->field_type);
			}
		}
		$db->update('products_type_fields', $set, array(
      'field_name' => $fieldName,
      'type' => $type,
		));
		cache::remove('product-type-fields-' . $type);
		$affected = (boolean)$db->affected();
		return $affected;
	}

	/**
	 * 删除商品类型字段
	 * @param string $type 商品类型标识
	 * @param string $fieldName 属性类型标识
	 */
	public function deleteTypeField($type, $fieldName)
	{
		global $db;
		if (!$field = $this->getTypeFieldInfo($type, $fieldName)) {
			return false;
		}
		$db->delete('products_type_fields', array(
      'field_name' => $fieldName,
      'type' => $type,
		));
		cache::remove('product-type-fields-' . $type);
		$affected = (boolean)$db->affected();
		if ($affected) {
			$this->dropTypeField($type, $field);
		}
		return $affected;
	}

	/**
	 * 获取商品类型字段扩展表名称
	 * @param string $type 类型标识
	 * @param string $fieldName 字段名
	 * @return string
	 */
	private function getTypeFieldTableName($type, $fieldName)
	{
		return strtolower('field_' . $type . '_' . $fieldName);
	}

	/**
	 * 获取商品类型字段名称
	 * @param string $fieldName 字段名
	 * @return string
	 */
	public function getTypeFieldName($fieldName)
	{
		return strtolower('field_' . $fieldName);
	}

	/**
	 * 获取商品类型表字段类型
	 * @param array $field 字段描述数组
	 */
	private function getTypeFieldType($field)
	{
		switch ($field['field_type']) {
			case self::FIELD_TYPE_TEXT:
				if ($field['field_size'] > 0) {
					$fieldType = 'VARCHAR(' . $field['field_size'] . ') DEFAULT ""';
				} else {
					$fieldType = 'TEXT';
				}
				break;
			case self::FIELD_TYPE_INTEGER:
				$fieldType = 'INT DEFAULT 0';
				break;
			case self::FIELD_TYPE_DATETIME:
				$fieldType = 'CHAR(10) DEFAULT ""';
				break;
			case self::FIELD_TYPE_FILE:
				global $db;
				$fieldType = 'INT UNSIGNED NOT NULL DEFAULT 0';
				break;
		}
		return $fieldType;
	}

	/**
	 * 创建商品类型表字段
	 * @param string $type 商品类型标识
	 * @param mixed $field 字段描述数组
	 */
	private function createTypeField($type, $field)
	{
		global $db;
		if (is_object($field)) {
			$field = (array)$field;
		}
		$fieldName = $field['field_name'];
		$fieldType = $this->getTypeFieldType($field);
		$typeFieldName = $db->escape($this->getTypeFieldName($fieldName));
		$indexLength = min(10, intval($field['field_size']));
		$indexLength = $field['field_type'] == self::FIELD_TYPE_TEXT ? (' (' . ($indexLength > 0 ? $indexLength : 10) . ')') : '';
		if ($field['multiple']) {
			$db->exec('CREATE TABLE IF NOT EXISTS `' . $db->escape($this->getTypeFieldTableName($type, $fieldName)) . '` (
        `pid` INT UNSIGNED NOT NULL,
        `delta` INT UNSIGNED NOT NULL DEFAULT 0,
        `' . $typeFieldName . '` ' . $fieldType .
			($field['valued'] ? (', `' . $typeFieldName . '_value` DECIMAL(11,2) DEFAULT 0') : '') .
        ', PRIMARY KEY (`pid`, `delta`)' .
			($field['indexed'] ? ', INDEX `' . $typeFieldName . '` (`' . $typeFieldName . '`' . $indexLength . ' ASC)' : '') .
        ') DEFAULT CHARACTER SET = utf8
        COLLATE = utf8_general_ci');
		} else {
			$db->exec('ALTER TABLE `' . $db->escape($this->getTypeTableName($type)) . '` ADD COLUMN `' .
			$typeFieldName . '` ' . $fieldType .
			($field['valued'] ? (', ADD COLUMN `' . $typeFieldName . '_value` DECIMAL(11,2) DEFAULT 0') : '') .
			($field['indexed'] ? ', ADD INDEX `' . $typeFieldName . '` (`' . $typeFieldName . '`' . $indexLength . ' ASC)' : ''));
		}
	}

	/**
	 * 修改商品类型表字段
	 * @param string $type 商品类型标识
	 * @param mixed $field 字段描述数组
	 */
	private function modifyTypeField($type, $field)
	{
		global $db;
		if (is_object($field)) {
			$field = (array)$field;
		}
		$fieldName = $field['field_name'];
		$fieldType = $this->getTypeFieldType($field);
		$typeFieldName = $db->escape($this->getTypeFieldName($fieldName));
		$indexLength = min(10, intval($field['field_size']));
		$indexLength = $field['field_type'] == self::FIELD_TYPE_TEXT ? (' (' . ($indexLength > 0 ? $indexLength : 10) . ')') : '';
		if ($field['org_indexed'] != $field['indexed']) {
			if ($field['indexed']) {
				$indexSql = ', ADD INDEX `' . $typeFieldName . '` (`' . $typeFieldName . '`' . $indexLength . ' ASC)';
			} else {
				$indexSql = ', DROP INDEX `' . $typeFieldName . '`';
			}
		} else if ($field['indexed'] && $field['field_type'] == self::FIELD_TYPE_TEXT) {
			$indexSql = ', DROP INDEX `' . $typeFieldName . '`, ADD INDEX `' . $typeFieldName . '` (`' . $typeFieldName . '`' . $indexLength . ' ASC)';
		} else {
			$indexSql = '';
		}
		if ($field['multiple']) {
			$db->exec('ALTER TABLE `' . $db->escape($this->getTypeFieldTableName($type, $fieldName)) . '` MODIFY COLUMN `' .
			$typeFieldName . '` ' . $fieldType . $indexSql);
		} else {
			$db->exec('ALTER TABLE `' . $db->escape($this->getTypeTableName($type)) . '` MODIFY COLUMN `' .
			$typeFieldName . '` ' . $fieldType . $indexSql);
		}
	}

	/**
	 * 删除商品类型表字段
	 * @param string $type 商品类型标识
	 * @param mixed $field 字段描述数组
	 */
	private function dropTypeField($type, $field)
	{
		global $db;
		if (is_object($field)) {
			$field = (array)$field;
		}
		$fieldName = $field['field_name'];
		$typeFieldName = $db->escape($this->getTypeFieldName($fieldName));
		if ($field['multiple']) {
			$db->exec('DROP TABLE IF EXISTS `' . $db->escape($this->getTypeFieldTableName($type, $fieldName)) . '`');
		} else {
			$db->exec('ALTER TABLE `' . $db->escape($this->getTypeTableName($type)) . '` DROP COLUMN `' .
			$typeFieldName . '`' .
			($field['valued'] ? (', DROP COLUMN `' . $typeFieldName . '_value`') : '') .
			($field['indexed'] ? ', DROP INDEX `' . $typeFieldName . '`' : ''));
		}
	}

	/**
	 * 获取商品数量
	 * @param string $type 商品类型标识
	 * @return int
	 */
	public function getProductsCount($post = array())
	{
		global $db;
		$filter = array(
      'pid' => isset($post['pid']) && trim($post['pid']) !== '' ? trim($post['pid']) : null,
      'name LIKE' => isset($post['name']) && trim($post['name']) !== '' ? ('%' . trim($post['name']) . '%') : null,
      'sn LIKE' => isset($post['sn']) && trim($post['sn']) !== '' ? ('%' . trim($post['sn']) . '%') : null,
      'number LIKE' => isset($post['number']) && trim($post['number']) !== '' ? ('%' . trim($post['number']) . '%') : null,
      'type' => isset($post['type']) && trim($post['type']) !== '' ? $post['type'] : null,
      'brand_tid IN' => isset($post['brand_tid']) && $post['brand_tid'] ? $post['brand_tid'] : null,
      'sell_price >=' => isset($post['lowprice']) && is_numeric($post['lowprice']) ? intval($post['lowprice']) : null,
      'sell_price <=' => isset($post['highprice']) && is_numeric($post['highprice']) ? intval($post['highprice']) : null,
      'status' => isset($post['status']) && $post['status'] !== '' ? intval($post['status']) : null,
      'free_shipping' => isset($post['free_shipping']) && $post['free_shipping'] !== '' ? intval($post['free_shipping']) : null,
		);
		if (isset($post['tids']) && $post['tids']) {
			$taxonomyInstance = Taxonomy_Model::getInstance();
			$termInfo = $taxonomyInstance->getTermInfo($post['tids']);
			if (isset($termInfo) && $termInfo) {
				if (!$termInfo->ptid1) {
					$filter['directory_tid1'] = $termInfo->tid;
				} else if (!$termInfo->ptid2) {
					$filter['directory_tid1'] = $termInfo->ptid1;
					$filter['directory_tid2'] = $termInfo->tid;
				} else if (!$termInfo->ptid3) {
					$filter['directory_tid1'] = $termInfo->ptid1;
					$filter['directory_tid2'] = $termInfo->ptid2;
					$filter['directory_tid3'] = $termInfo->tid;
				} else {
					$filter['directory_tid1'] = $termInfo->ptid1;
					$filter['directory_tid2'] = $termInfo->ptid2;
					$filter['directory_tid3'] = $termInfo->ptid3;
					$filter['directory_tid4'] = $termInfo->tid;
				}
			}
		}
		$db->select('COUNT(0)');
		foreach ($filter as $key => $value) {
			if (isset($value) && $value !== '' && $value !== false) {
				$db->where($key, $value);
			}
		}
		$router = Bl_Core::getRouter();
		$undercarriageShow = Bl_Config::get('undercarriageShow');
		if ($router['folder'] != 'admin') {
			$db->where('status >=', 0);
			if (!isset($undercarriageShow) || !$undercarriageShow) {
				$db->where('status', 1);
			}
			$noStockHidden = Bl_Config::get('noStockHidden');
			if(isset($noStockHidden) && $noStockHidden){
				$db->where('stock >', 0);
			}
		}
		$result = $db->get('products');
		return $result->one();
	}

	/**
	 * 获取商品列表
	 */
	public function getProductsList($post = array(), $page = null, $pageRows = 60)
	{
		global $db;
		// TODO
		if (!$pageRows) {
			$pageRows = 60;
		}
		$filter = array(
      'pid' => isset($post['pid']) && trim($post['pid']) !== '' ? trim($post['pid']) : null,
      'name LIKE' => isset($post['name']) && trim($post['name']) !== '' ? ('%' . trim($post['name']) . '%') : null,
      'sn LIKE' => isset($post['sn']) && trim($post['sn']) !== '' ? ('%' . trim($post['sn']) . '%') : null,
      'number LIKE' => isset($post['number']) && trim($post['number']) !== '' ? ('%' . trim($post['number']) . '%') : null,
      'type' => isset($post['type']) && trim($post['type']) !== '' ? $post['type'] : null,
      'brand_tid IN' => isset($post['brand_tid']) && $post['brand_tid'] ? $post['brand_tid'] : null,
      'sell_price >=' => isset($post['lowprice']) && is_numeric($post['lowprice']) ? intval($post['lowprice']) : null,
      'sell_price <=' => isset($post['highprice']) && is_numeric($post['highprice']) ? intval($post['highprice']) : null,
      'status' => isset($post['status']) && $post['status'] !== '' ? intval($post['status']) : null,
      'free_shipping' => isset($post['free_shipping']) && $post['free_shipping'] !== '' ? intval($post['free_shipping']) : null,
		);
		if (isset($post['tids']) && $post['tids']) {
			$taxonomyInstance = Taxonomy_Model::getInstance();
			$termInfo = $taxonomyInstance->getTermInfo($post['tids']);
			if (isset($termInfo) && $termInfo) {
				if (!$termInfo->ptid1) {
					$filter['directory_tid1'] = $termInfo->tid;
				} else if (!$termInfo->ptid2) {
					$filter['directory_tid1'] = $termInfo->ptid1;
					$filter['directory_tid2'] = $termInfo->tid;
				} else if (!$termInfo->ptid3) {
					$filter['directory_tid1'] = $termInfo->ptid1;
					$filter['directory_tid2'] = $termInfo->ptid2;
					$filter['directory_tid3'] = $termInfo->tid;
				} else {
					$filter['directory_tid1'] = $termInfo->ptid1;
					$filter['directory_tid2'] = $termInfo->ptid2;
					$filter['directory_tid3'] = $termInfo->ptid3;
					$filter['directory_tid4'] = $termInfo->tid;
				}
			}
		}
		if(isset($filter['level'])){
			if ($filter['level'] == 1) {
				$filter['tids'] = isset($filter['directory_tid1']) ? $filter['directory_tid1'] : $filter['directory_tid'];
			}elseif ($filter['level'] == 'self') {
				$filter['tids'] = $filter['directory_tid'];
			}elseif ($filter['level'] == 'all') {
				$filter['tids'] = null;
			}
		}
		$db->select('pid');
		foreach ($filter as $key => $value) {
			if (isset($value) && $value !== '' && $value !== false) {
				$db->where($key, $value);
			}
		}
		$router = Bl_Core::getRouter();
		$undercarriageShow = Bl_Config::get('undercarriageShow');
		if ($router['folder'] != 'admin') {
			$db->where('status >=', 0);
			if (!isset($undercarriageShow) || !$undercarriageShow) {
				$db->where('status', 1);
			}
			$noStockHidden = Bl_Config::get('noStockHidden');
			if(isset($noStockHidden) && $noStockHidden){
				$db->where('stock >', 0);
			}
		}
		if (isset($post['orderby']) && $post['orderby']) {
			$db->orderby($post['orderby']);
		} else {
			$db->orderby('status DESC, weight DESC, created DESC, pid DESC');
		}
		if (isset($page) && $pageRows != 'all') {
			$db->limitPage($pageRows, $page);
		}
		$result = $db->get('products');
		$pids = $result->column();
		$products = array();
		foreach ($pids as $pid) {
			$products[$pid] = $this->getProductInfo($pid);
			$products[$pid]->related = $this->listProductRelated($pid, 1, 5);
		}
		return $products;
	}

	public function getProductsCountBySpecial($post = array())
	{
		global $db;
		$filter = array(
      'p.pid' => isset($post['pid']) && trim($post['pid']) !== '' ? trim($post['pid']) : null,
      'p.name LIKE' => isset($post['name']) && trim($post['name']) !== '' ? ('%' . trim($post['name']) . '%') : null,
      'p.sn LIKE' => isset($post['sn']) && trim($post['sn']) !== '' ? ('%' . trim($post['sn']) . '%') : null,
      'p.number LIKE' => isset($post['number']) && trim($post['number']) !== '' ? ('%' . trim($post['number']) . '%') : null,
      'p.type' => isset($post['type']) && trim($post['type']) !== '' ? $post['type'] : null,
      'p.sell_price >=' => isset($post['lowprice']) && is_numeric($post['lowprice']) ? intval($post['lowprice']) : null,
      'p.sell_price <=' => isset($post['highprice']) && is_numeric($post['highprice']) ? intval($post['highprice']) : null,
      'p.status' => isset($post['status']) && $post['status'] !== '' ? intval($post['status']) : null,
      'p.free_shipping' => isset($post['free_shipping']) && $post['free_shipping'] !== '' ? intval($post['free_shipping']) : null,
		);
		if (isset($post['tids']) && $post['tids']) {
			$taxonomyInstance = Taxonomy_Model::getInstance();
			$termInfo = $taxonomyInstance->getTermInfo($post['tids']);
			if (isset($termInfo) && $termInfo) {
				if (!$termInfo->ptid1) {
					$filter['p.directory_tid1'] = $termInfo->tid;
				} else if (!$termInfo->ptid2) {
					$filter['p.directory_tid1'] = $termInfo->ptid1;
					$filter['p.directory_tid2'] = $termInfo->tid;
				} else if (!$termInfo->ptid3) {
					$filter['p.directory_tid1'] = $termInfo->ptid1;
					$filter['p.directory_tid2'] = $termInfo->ptid2;
					$filter['p.directory_tid3'] = $termInfo->tid;
				} else {
					$filter['p.directory_tid1'] = $termInfo->ptid1;
					$filter['p.directory_tid2'] = $termInfo->ptid2;
					$filter['p.directory_tid3'] = $termInfo->ptid3;
					$filter['p.directory_tid4'] = $termInfo->tid;
				}
			}
		}
		$db->select('COUNT(0)');
		foreach ($filter as $key => $value) {
			if (isset($value) && $value !== '' && $value !== false) {
				$db->where($key, $value);
			}
		}
		$router = Bl_Core::getRouter();
		$undercarriageShow = Bl_Config::get('undercarriageShow');
		if ($router['folder'] != 'admin') {
			$db->where('p.status >=', 0);
			if (!isset($undercarriageShow) || !$undercarriageShow) {
				$db->where('p.status', 1);
			}
			$noStockHidden = Bl_Config::get('noStockHidden');
			if(isset($noStockHidden) && $noStockHidden){
				$db->where('p.stock >', 0);
			}
		}
		if (isset($post['termname']) && $post['termname']) {
			$db->where('t.name', $post['termname']);
		}
		if (isset($post['special_tid']) && $post['special_tid']) {
			$db->where('t.tid', $post['special_tid']);
		}
		$db->join('terms t', 't.tid = tp.tid');
		$db->join('products p', 'tp.pid = p.pid');
		$result = $db->get('terms_products tp');
		return $result->one();
	}

	/**
	 * 获取特殊类型的商品数据 商品目录可以和特殊类型分类（推荐商品）组合使用
	 */
	public function getProductsListBySpecial($post = array(), $page = 1, $pageRows = 60)
	{
		global $db;
		// TODO
		if (!$pageRows) {
			$pageRows = 60;
		}
		$filter = array(
      'p.pid' => isset($post['pid']) && trim($post['pid']) !== '' ? trim($post['pid']) : null,
      'p.name LIKE' => isset($post['name']) && trim($post['name']) !== '' ? ('%' . trim($post['name']) . '%') : null,
      'p.sn LIKE' => isset($post['sn']) && trim($post['sn']) !== '' ? ('%' . trim($post['sn']) . '%') : null,
      'p.number LIKE' => isset($post['number']) && trim($post['number']) !== '' ? ('%' . trim($post['number']) . '%') : null,
      'p.type' => isset($post['type']) && trim($post['type']) !== '' ? $post['type'] : null,
      'p.sell_price >=' => isset($post['lowprice']) && is_numeric($post['lowprice']) ? intval($post['lowprice']) : null,
      'p.sell_price <=' => isset($post['highprice']) && is_numeric($post['highprice']) ? intval($post['highprice']) : null,
      'p.status' => isset($post['status']) && $post['status'] !== '' ? intval($post['status']) : null,
      'p.free_shipping' => isset($post['free_shipping']) && $post['free_shipping'] !== '' ? intval($post['free_shipping']) : null,
      'p.brand_tid' => isset($post['brand_tid']) && $post['brand_tid'] !== '' ? intval($post['brand_tid']) : null,
		);

		if (isset($post['tids']) && $post['tids']) {
			$taxonomyInstance = Taxonomy_Model::getInstance();
			$termInfo = $taxonomyInstance->getTermInfo($post['tids']);
			if (isset($termInfo) && $termInfo) {
				if (!$termInfo->ptid1) {
					$filter['p.directory_tid1'] = $termInfo->tid;
				} else if (!$termInfo->ptid2) {
					$filter['p.directory_tid1'] = $termInfo->ptid1;
					$filter['p.directory_tid2'] = $termInfo->tid;
				} else if (!$termInfo->ptid3) {
					$filter['p.directory_tid1'] = $termInfo->ptid1;
					$filter['p.directory_tid2'] = $termInfo->ptid2;
					$filter['p.directory_tid3'] = $termInfo->tid;
				} else {
					$filter['p.directory_tid1'] = $termInfo->ptid1;
					$filter['p.directory_tid2'] = $termInfo->ptid2;
					$filter['p.directory_tid3'] = $termInfo->ptid3;
					$filter['p.directory_tid4'] = $termInfo->tid;
				}
			}
		}
		$db->select('p.*, IFNULL(t.path_alias, "") tpath_alias');
		foreach ($filter as $key => $value) {
			if (isset($value) && $value !== '' && $value !== false) {
				$db->where($key, $value);
			}
		}
		$router = Bl_Core::getRouter();
		$undercarriageShow = Bl_Config::get('undercarriageShow');
		if ($router['folder'] != 'admin') {
			$db->where('p.status >=', 0);
			if (!isset($undercarriageShow) || !$undercarriageShow) {
				$db->where('p.status', 1);
			}
			$noStockHidden = Bl_Config::get('noStockHidden');
			if(isset($noStockHidden) && $noStockHidden){
				$db->where('p.stock >', 0);
			}
		}
		if (isset($post['termname']) && $post['termname']) {
			$db->where('t.name', $post['termname']);
		}
		if (isset($post['special_tid']) && $post['special_tid']) {
			$db->where('t.tid', $post['special_tid']);
		}
		$db->join('terms t', 't.tid = tp.tid');
		$db->join('products p', 'tp.pid = p.pid');
		if (isset($post['orderby']) && $post['orderby']) {
			$db->orderby($post['orderby']);
		} else {
			$db->orderby('p.status DESC, p.weight DESC, p.pid DESC');
		}
		if (isset($pageRows) && $pageRows != 'all') {
			$db->limitPage($pageRows, $page);
		}
		$result = $db->get('terms_products tp');
		$products = $result->allWithKey('pid');
		foreach ($products as &$product) {
			$product = $this->getProductInfo($product->pid);
			$product->related = $this->listProductRelated($product->pid, 1, 5);
		}
		return $products;
	}


	public function getProductTypeAndTypeField(&$product){
		global $db;
		$type = $product->type;
		$pid = $product->pid;
		if ($this->checkTypeExist($type)) {
			$result = $db->query('SELECT * FROM `' . $this->getTypeTableName($type) . '` WHERE pid = ' . $pid);
			if ($ext = $result->row(false)) {
				foreach ($ext as $key => $value) {
					$product->{$key} = $value;
				}
			}
			$fieldsList = $this->getTypeFieldsList($type);
			$fileInstance = File_Model::getInstance();
			foreach ($fieldsList as $fieldName => $field) {
				$getFieldName = $this->getTypeFieldName($fieldName);
				if ($field->multiple) {
					if ($field->valued) {
						$result = $db->query('SELECT `' . $getFieldName . '` v, `' . $getFieldName . '_value` p FROM `' .
						$this->getTypeFieldTableName($type, $fieldName) .
                    '` WHERE pid = ' . $pid . ' ORDER BY delta ASC');
						$values = $result->all();
						$product->{$getFieldName} = array();
						$product->{$getFieldName . '_value'} = array();
						foreach ($values as $val) {
							$product->{$getFieldName}[] = $val->v;
							$product->{$getFieldName . '_value'}[] = $val->p;
						}
					} else {
						$result = $db->query('SELECT `' . $getFieldName . '` FROM `' .
						$this->getTypeFieldTableName($type, $fieldName) .
                    '` WHERE pid = ' . $pid . ' ORDER BY delta ASC');
						$product->{$getFieldName} = $result->column();
					}
					if ($field->field_type == Product_Model::FIELD_TYPE_FILE) {
						foreach ($product->{$getFieldName} as &$val) {
							$val = $fileInstance->getFileInfo($val);
						}
					}
				} else if ($field->field_type == Product_Model::FIELD_TYPE_FILE && isset($product->{$getFieldName})) {
					$product->{$getFieldName} = $fileInstance->getFileInfo($product->{$getFieldName});
				}
			}
		}
	}

	/**
	 * 获取商品信息
	 * @param int $pid 商品ID
	 * @return object
	 */
	public function getProductInfo($pid)
	{
		global $db, $user;
		static $list = array();
		if (!isset($list[$pid])) {
			$cacheId = 'product-' . $pid;
			if ($cache = cache::get($cacheId)) {
				$list[$pid] = $cache->data;
			} else {
				$undercarriageShow = Bl_Config::get('undercarriageShow');
				$router = Bl_Core::getRouter();
				if ($router['folder'] != 'admin') {
					$db->where('status >', -1);
					if (!isset($undercarriageShow) || !$undercarriageShow) {
						$db->where('status', 1);
					}
					$noStockHidden = Bl_Config::get('noStockHidden');
					if(isset($noStockHidden) && $noStockHidden){
						$db->where('stock >', 0);
					}
				}
				$db->where('pid', $pid);
				$result = $db->get('products');
				$product = $result->row();
				if ($product) {
					$this->getProductTypeAndTypeField(&$product);
					$product->directory_tid =  $product->directory_tid4 ? $product->directory_tid4 : (
					$product->directory_tid3 ? $product->directory_tid3 : (
					$product->directory_tid2 ? $product->directory_tid2 : (
					$product->directory_tid1 ? $product->directory_tid1 : 0
					)
					)
					);
					//we don't need add category url here. There is no term path alias added in the product url.
					/*
					*
					 
					if ($product->directory_tid) {
					$taxonomyInstance = Taxonomy_Model::getInstance();
					$term = $taxonomyInstance->getTermInfo($product->directory_tid);
					$termPathAlias = isset($term->path_alias) && $term->path_alias !== '' ? $term->path_alias : 'product';
					} else {
					$termPathAlias = 'product';
					}
					*/
					$product->tags = $this->getProductTags($product->pid);
					$product->url = ($product->path_alias !== '' ? $product->path_alias : $product->pid).'-p'.$product->sn . '.html';
					//$product->url = $termPathAlias . '/' . ($product->path_alias !== '' ? $product->path_alias : $product->pid) . '.html';
					if (isset($product) && $product) {
						$this->getProductRealPrice($product);
					}
					if (isset($product->directory_tid) && $product->directory_tid) {
						$db->select('path_alias, name');
						$db->where('tid', $product->directory_tid);
						$router = Bl_Core::getRouter();
						if ($router['folder'] != 'admin') {
							$db->where('visible', 1);
						}
						$result = $db->get('terms');
						$termInfo = $result->row();
						$product->tpath_alias = isset($termInfo->path_alias) ? $termInfo->path_alias : 'product';
						$product->tname = isset($termInfo->name) ? $termInfo->name : 'product';
					}
					$commentInstance = Comment_Model::getInstance();
					$product->comments = $commentInstance->getCommentsListByProductId($product->pid, $filter = array('status' => 1), 1, 12);
					$list[$pid] = $product;
					/*
					 $product_new = callFunction('product_info_after', $product);
					 if($product_new){
					 $product = $product_new;
					 }
					 */

					cache::save($cacheId, $product);
				}
			}
		}
		return isset($list[$pid]) ? $list[$pid] : null;
	}

	/**
	 * 获取商品真实价格
	 * @param object $product 商品对象
	 */
	public function getProductRealPrice(&$product)
	{
		global $user;
		$pid = $product->pid;
		if($user){
			$rid = $user->rid;
			if (false !== ($promotionsInfo = $this->getProductPromotionsPriceByRID($pid, $rid))) {
				$product->price = $promotionsInfo->price;
				$product->promotion = $promotionsInfo;
			} else if (false !== ($ranksPrice = $this->getProductRanksPriceByRID($pid, $rid))) {
				$product->price = $ranksPrice;
			} else {
				$userInstance = User_Model::getInstance();
				$ranksList = $userInstance->getRanksList();
				if ($ranksList[$rid]->discount && $ranksList[$rid]->discount > 0) {
					$product->price = round($ranksList[$rid]->discount * $product->sell_price / 100, 2);
				} else {
					$product->price = $product->sell_price;
				}
			}
		}
		callFunction('getProductInfo', $product);
	}

	/**
	 * 获取商品信息 (按路径别名)
	 * @param string $path 路径别名
	 * @return object
	 */
	public function getProductInfoByPathAlias($path)
	{
		global $db;
		static $list = array();
		if (!isset($list[$path])) {
			$result = $db->query('SELECT pid FROM products WHERE path_alias = "' . $db->escape($path) . '"');
			$list[$path] = $result->one();
		}
		return $list[$path] ? $this->getProductInfo($list[$path]) : $list[$path];
	}

	/**
	 * 获取商品信息 (按货号)
	 * @param string $sn
	 * @return object
	 */
	public function getProductInfoBySn($sn)
	{
		global $db;
		static $list = array();
		if (!isset($list[$sn])) {
			$result = $db->query('SELECT pid FROM products WHERE sn = "' . $db->escape($sn) . '"');
			$list[$sn] = $result->one();
		}
		return $list[$sn] ? $this->getProductInfo($list[$sn]) : $list[$sn];
	}

	/**
	 * 获取相同路径别名前缀的列表
	 * @param string $path 路径别名
	 * @return array
	 */
	public function getProductPathAliasList($path)
	{
		global $db;
		$result = $db->query('SELECT pid, path_alias FROM products WHERE path_alias LIKE "' . $db->escape($path) . '%"');
		return $result->columnWithKey('pid', 'path_alias');
	}

	/**
	 * 新建商品
	 * @param array $post 商品表单数组
	 * @return int 商品ID
	 */
	public function insertProduct($post)
	{
		global $db;
		if (isset($post['name']) && '' == $post['name']) {
			return false;
		}
		if (isset($post['directory_tid']) && $post['directory_tid']) {
			$taxonomyInstance = Taxonomy_Model::getInstance();
			$termInfo = $taxonomyInstance->getTermInfo($post['directory_tid']);
			if (isset($termInfo) && $termInfo) {
				$post['directory_tid1'] = $post['directory_tid2'] = $post['directory_tid3'] = $post['directory_tid4'] = 0;
				if (!$termInfo->ptid1) {
					$post['directory_tid1'] = $termInfo->tid;
				} else if (!$termInfo->ptid2) {
					$post['directory_tid1'] = $termInfo->ptid1;
					$post['directory_tid2'] = $termInfo->tid;
				} else if (!$termInfo->ptid3) {
					$post['directory_tid1'] = $termInfo->ptid1;
					$post['directory_tid2'] = $termInfo->ptid2;
					$post['directory_tid3'] = $termInfo->tid;
				} else {
					$post['directory_tid1'] = $termInfo->ptid1;
					$post['directory_tid2'] = $termInfo->ptid2;
					$post['directory_tid3'] = $termInfo->ptid3;
					$post['directory_tid4'] = $termInfo->tid;
				}
			}
		}
		unset($post['directory_tid']);
		$set = $post;
		$set['created'] = TIMESTAMP;
		$set['updated'] = TIMESTAMP;
		$db->insert('products', $set);
		cache::remove('product-type');
		$pid = $db->lastInsertId();
		if (isset($pid) && $pid) {
			callFunction('productinsert', $pid);
			return $pid;
		}
	}

	/**
	 * 新建商品扩展字段
	 * @param int $pid 商品ID
	 * @param string $type 商品类型
	 * @param array $post 商品表单数组
	 * @return void
	 */
	public function insertProductFields($pid, $type, $post)
	{
		global $db;
		$fieldsList = $this->getTypeFieldsList($type);
		if ($fieldsList) {
			$set = array();
			$fileInstance = File_Model::getInstance();
			foreach ($fieldsList as $fieldName => $field) {
				if (isset($post->{'field_' . $fieldName}) || isset($_FILES)) {
					$setFieldName = $this->getTypeFieldName($fieldName);
					if ($field->multiple) {
						if ($field->display_type == self::DISPLAY_TYPE_FILE) {
							$data = isset($_FILES['field_' . $fieldName]) ? $_FILES['field_' . $fieldName]['name'] : array();
						} else {
							$data = $post->{'field_' . $fieldName};
						}
						foreach ($data as $delta => $value) {
							if ($field->display_type == self::DISPLAY_TYPE_FILE) {
								$file = $fileInstance->insertFile('field_' . $fieldName, array('type' => 'product_' . $fieldName), $delta);
								if ($file) {
									$value = $file->fid;
								} else {
									continue;
								}
							}
							$rowSet = array(
                'pid' => $pid,
                'delta' => $delta,
							$setFieldName => $value,
							);
							if ($field->valued) {
								$rowSet[$setFieldName . '_value'] = (isset($post->{'field_' . $fieldName . '_value'}[$delta]) && $post->{'field_' . $fieldName . '_value'}[$delta]) ? $post->{'field_' . $fieldName . '_value'}[$delta] : 0;
							}
							$db->insert($this->getTypeFieldTableName($type, $fieldName), $rowSet);
						}
					} else {
						if ($field->display_type == self::DISPLAY_TYPE_FILE && isset($_FILES['field_' . $fieldName])) {
							$file = $fileInstance->insertFile('field_' . $fieldName, array('type' => 'product_' . $fieldName));
							if ($file) {
								$set[$setFieldName] = $file->fid;
							}
						} else {
							$set[$setFieldName] = $post->{'field_' . $fieldName};
						}
						if ($field->valued) {
							$set[$setFieldName . '_value'] = (isset($post->{'field_' . $fieldName . '_value'}) && $post->{'field_' . $fieldName . '_value'}) ? $post->{'field_' . $fieldName . '_value'} : 0;
						}
					}
				}
			}
			if (!empty($set)) {
				$db->update($this->getTypeTableName($type), $set, array('pid' => $pid));
				if (!$db->affected()) {
					$set['pid'] = $pid;
					$db->insert($this->getTypeTableName($type), $set, true);
				}
			}
		}
	}

	/**
	 * 修改商品
	 * @param int $pid 商品ID
	 * @param array $post 商品表单内容
	 * @return boolean
	 */
	public function updateProduct($pid, $post)
	{
		global $db;
		if (isset($post['name']) && '' == $post['name']) {
			return false;
		}
		if (isset($post['directory_tid']) && $post['directory_tid']) {
			$taxonomyInstance = Taxonomy_Model::getInstance();
			$termInfo = $taxonomyInstance->getTermInfo($post['directory_tid']);
			if (isset($termInfo) && $termInfo) {
				$post['directory_tid1'] = $post['directory_tid2'] = $post['directory_tid3'] = $post['directory_tid4'] = 0;
				if (!$termInfo->ptid1) {
					$post['directory_tid1'] = $termInfo->tid;
				} else if (!$termInfo->ptid2) {
					$post['directory_tid1'] = $termInfo->ptid1;
					$post['directory_tid2'] = $termInfo->tid;
				} else if (!$termInfo->ptid3) {
					$post['directory_tid1'] = $termInfo->ptid1;
					$post['directory_tid2'] = $termInfo->ptid2;
					$post['directory_tid3'] = $termInfo->tid;
				} else {
					$post['directory_tid1'] = $termInfo->ptid1;
					$post['directory_tid2'] = $termInfo->ptid2;
					$post['directory_tid3'] = $termInfo->ptid3;
					$post['directory_tid4'] = $termInfo->tid;
				}
			}
		}
		unset($post['directory_tid']);
		$set = $post;
		$set['updated'] = TIMESTAMP;
		$db->update('products', $set, array('pid' => $pid));
		cache::remove('product-' . $pid);
		cache::remove('product-related-' . $pid);
		$db->affected();
		return true;
	}

	/**
	 * 修改商品扩展字段
	 * @param int $pid 商品ID
	 * @param string $type 商品类型
	 * @param array $post 商品表单数组
	 * @return void
	 */
	public function updateProductFields($pid, $type, $post)
	{
		$this->deleteProductFields($pid, $type);
		$this->insertProductFields($pid, $type, $post);
	}

	/**
	 * 删除商品扩展字段
	 * @param int $pid 商品ID
	 * @param string $type 商品类型
	 * @return void
	 */
	public function deleteProductFields($pid, $type)
	{
		global $db;
		$fieldsList = $this->getTypeFieldsList($type);
		if ($fieldsList) {
			foreach ($fieldsList as $fieldName => $field) {
				if ($field->multiple) {
					$db->delete($this->getTypeFieldTableName($type, $fieldName), array('pid' => $pid));
				}
			}
		}
	}

	public function concatProductSearchKey($pid, $searchKey)
	{
		global $db;
		$sql = 'UPDATE products SET sphinx_key=CONCAT(sphinx_key," ","' . $db->escape($searchKey)  . '") WHERE pid="' . $pid . '";';

		$db->exec($sql);
	}

	/**
	 * 删除商品
	 * @param int $pid 商品ID
	 * @return boolean
	 */
	public function deleteProduct($pid)
	{
		global $db;
		if ($productInfo = $this->getProductInfo($pid)) {
			$sqlPid = $db->escape($pid);
			$db->exec('DELETE FROM products WHERE pid = ' . $sqlPid);
			$affected = $db->affected();
			if ($affected) {
				$this->deleteProductFields($pid, $productInfo->type);
				$db->exec('DELETE FROM products_relations WHERE pid = ' . $sqlPid . ' OR related_pid = ' . $sqlPid);
				$db->exec('DELETE FROM products_files WHERE pid = ' . $sqlPid);
				$db->exec('DELETE FROM products_comments WHERE pid = ' . $sqlPid);
				$db->exec('DELETE FROM terms_products WHERE pid = ' . $sqlPid);
				$db->exec('DELETE FROM cart_products WHERE pid = ' . $sqlPid);
				$db->exec('DELETE FROM promotions_products WHERE pid = ' . $sqlPid);
				$db->exec('DELETE FROM products_ranks WHERE pid = ' . $sqlPid);
				$db->exec('DELETE FROM articles_products WHERE pid = ' . $sqlPid);
				$db->exec('DELETE FROM orders_items WHERE pid = ' . $sqlPid);
				cache::remove('product-type');
				cache::remove('product-' . $pid);
			}
		}
		return (boolean)$affected;
	}

	/**
	 * 获取商品关联的商品信息
	 */
	public function getProductRelated()
	{

	}

	/**
	 * 获取相关商品信息
	 * @param int $pid
	 * @param int $page
	 * @param int $pageRows
	 */
	public function listProductRelated($pid, $page = 0, $pageRows = 60)
	{
		global $db;
		// TODO
		if (!$pageRows) {
			$pageRows = 60;
		}
		static $list = array();
		if (!isset($list[$pid])) {
			$cacheId = 'product-related-' . $pid;
			if ($cache = cache::get($cacheId)) {
				$list[$pid] = $cache->data;
			} else {
				$data = array();
				$db->select('related_pid');
				$db->where('pid', $pid);
				if ($pageRows && $pageRows != 'all') {
					$db->limitPage($pageRows, $page);
				}
				$result = $db->get('products_relations');
				$list = $result->column('related_pid');
				foreach ($list as $key => $dl) {
					if ($product = $this->getProductInfo($dl)) {
						$data[$dl] = $product;
					}
					$sql = "SELECT pid FROM products_relations WHERE pid = '".$dl."' AND related_pid = '".$pid."' ";
					$result2 = $db->query($sql);
					$pidExist = $result2->one();
					if (isset($data[$dl])) {
						if ($pidExist) {
							$data[$dl]->isbothway = 1;
						} else {
							$data[$dl]->isbothway = 0;
						}
					}
				}
				$list[$pid] = $data;
				cache::save($cacheId, $list[$pid]);
			}
		}
		return $list[$pid];
	}

	/**
	 *
	 * 获取相关商品总数
	 * @param int $pid
	 */
	public function countProductRelated($pid)
	{
		global $db;
		static $num;
		if(!isset($num)){
			$sql = "SELECT count(*) FROM products p INNER JOIN products_relations pr ON p.pid = pr.pid WHERE p.pid = '".$db->escape($pid)."'";
			$result = $db->query($sql);
			$num = $result->one();
		}
		return $num;
	}

	/**
	 *
	 * 删除相关的商品信息
	 * @param int $pid
	 * @param int $related_pid
	 */
	public function deleteProductRelated($pid, $related_pid)
	{
		global $db;
		$db->delete('products_relations', array(
      'pid' => $pid,
      'related_pid' => $related_pid,
		));
		return (boolean)$db->affected();
	}

	/**
	 *
	 * 更新相关商品信息
	 * @param int $pid
	 * @param array $post
	 */
	public function updateProductRelated($pid, $post)
	{
		global $db;
		$db->delete('products_relations', array('pid' => $pid));
		foreach ($post as $key => $dl){

			$set = array(
        'pid' => $pid,
        'related_pid' => $dl->pid,
			);
			$db->insert('products_relations', $set);
			if ($dl->isbothway == 1) {
				$set = array(
         'pid' => $dl->pid,
         'related_pid' => $pid,
				);
				$db->where('pid',$set['pid']);
				$db->where('related_pid',$set['related_pid']);
				$result = $db->get('products_relations');
				if(!(boolean)$result->one()){
					$db->insert('products_relations', $set);
				}
			} else {
				$db->delete('products_relations', array(
          'pid' => $dl->pid,
          'related_pid' => $pid,
				));
			}
		}
	}

	/**
	 *
	 * 更新产品附件信息
	 * @param int $pid
	 * @param int $fid
	 */
	public function updateProductFiles($pid, $post)
	{
		global $db;
		$db->delete('products_files', array('pid' => $pid));
		foreach ($post as $key => $dl) {
			$db->insert('products_files', array('pid' => $pid, 'fid' => $dl->fid));
			$db->update('files', array('alt' => $dl->alt), array('fid' => $dl->fid));
			$db->update('products_files', array('weight' => $dl->weight), array('fid' => $dl->fid, 'pid' => $pid));
		}
	}

	/**
	 *
	 * 新增产品附件信息
	 * @param int $pid
	 * @param int $fid
	 */
	public function insertProductFiles($pid, $post)
	{
		global $db;
		$db->delete('products_files', array('pid' => $pid, 'fid' => $post['fid']));
		$db->insert('products_files', array('weight' => $post['weight'],'fid' => $post['fid'], 'pid' => $pid));
	}

	/**
	 * 更新商品文件
	 * @param int $pid 商品ID
	 * @param int $fid 文件ID
	 */
	public function updateProductFile($pid, $fid)
	{
		global $db;
		$db->where('pid', $pid);
		$result = $db->get('products');
		$row = $result->row();
		if (!isset($row)) {
			return false;
		}
		if (!$row->fid) {
			$db->where('fid', $fid);
			$result = $db->get('files');
			$row_file = $result->row();
			if (!isset($row_file)) {
				return false;
			}
			$db->update('products', array('filepath' => $row_file->filepath), array('pid' => $pid));
			cache::remove('product-' . $pid);
		}
	}

	/**
	 * 获取商品所有文件列表信息
	 * @param $pid 商品ID
	 * @return object
	 */
	public function getProductFilesList($pid)
	{
		global $db;
		static $list = array();
		if (!isset($list[$pid])) {
			$sql = 'SELECT f.*, pf.weight FROM files f INNER JOIN products_files pf ON f.fid = pf.fid WHERE pid = "' .
			$db->escape($pid) . '" ORDER BY pf.weight DESC, f.fid ASC';
			$result = $db->query($sql);
			$list[$pid] = $result->allWithKey('fid');
		}
		return $list[$pid];
	}

	/**
	 * 增加商品访问数
	 * @param int $pid 商品ID
	 * @param int $increment 访问增量
	 */
	public function addProductVisit($pid, $increment = 1)
	{
		global $db;
		$set = array(
      'visits' => array(
        'escape' => false,
        'value' => 'visits + ' . $increment,
		),
		);
		$db->update('products', $set, array('pid' => $pid));
	}

	/**
	 * 检查商品库存信息
	 * @param int $pid 商品ID
	 * @param int $qty 购买数量
	 */
	public function checkProductStock($pid, $qty)
	{
		global $db;
		$result = $db->query('SELECT stock FROM products WHERE pid = ' . $db->escape($pid));
		return $qty <= intval($result->one());
	}

	/**
	 * 减少商品库存数
	 * @param int $pid 商品ID
	 * @param int $qty 减少的库存数
	 */
	public function updateProductStock($pid, $qty)
	{
		global $db;
		$set = array(
      'stock' => array(
        'escape' => false,
        'value' => 'stock - ' . $qty,
		),
		);
		$db->update('products', $set, array('pid' => $pid));
		if ($db->affected()) {
			callFunction('stock', $pid, $qty);
		}
		cache::remove('product-' . $pid);
	}

	/**
	 * 获取商品表字段名
	 * return object
	 */
	public function getProductFieldsName()
	{
		global $db;
		$result = mysql_query('SELECT * FROM products limit 1');
		$i = 0;
		while ($meta = mysql_fetch_field($result)) {
			if ($meta->name != 'pid' && $meta->name != 'type'){
				$array[$i] = $meta->name;
				$i++;
			}
		}
		mysql_free_result($result);
		return $array;
	}

	/**
	 * 获得商品的分类词ID列表, added by 55feng (2010-10-13)
	 * @param $pid, 要获取的商品的ID
	 */
	function getProductTerms($pid)
	{
		global $db;
		static $list = array();
		$pid = intval($pid);
		if($pid == 0){
			return false;
		}
		$sql = 'SELECT tid FROM terms_products WHERE pid="'.$db->escape($pid).'"';
		$result = $db->query($sql);
		$list = $result->column('tid');
		return $list;
	}

	/**
	 * 更新商品的分类词ID列表, added by 55feng (2010-10-13)
	 * @param $pid, 商品的ID
	 * @param $terms_products, 用户已选择的分类词ID列表
	 */
	function updateProductTerms($pid, $terms_products)
	{
		global $db;
		static $list = array();
		$table = 'terms_products';
		$pid = intval($pid);
		if($pid == 0){
			return false;
		}
		$db->delete($table, array('pid' => $pid));
		if(!is_array($terms_products) || count($terms_products)<1){
			return false;
		}
		foreach($terms_products as $key => $tid){
			$set = array(
         'tid' => $tid,
         'pid' => $pid,
			);
			$db->insert($table, $set);
		}
		return true;
	}

	function insertProductTerms($pid, $tid)
	{
		global $db;
		$db->insert('terms_products', array('pid' => $pid, 'tid' => $tid));
	}

	/**
	 * 批量编辑商品，用于获取下面选择框的商品列表
	 */
	function getBatchProductsNameList($filter)
	{
		global $db;
		static $list = array();
		$newFilter = array();
		foreach ($filter as $key => $value) {
			if (is_string($value)) $value = trim($value);
			if (!isset($value) || $value == null || $value == '') continue;
			if ((is_array($value) || is_object($value)) && count($value) < 1) continue;
			$newFilter[$key] = $value;
		}
		$filter = $newFilter;
		unset($newFilter);
		if (count($filter) < 1) return array();
		isset($filter['name']) ? $db->where('name LIKE', '%'.$filter['name'].'%') : '';
		isset($filter['sn']) ? $db->where('sn', $filter['sn']) : '';
		isset($filter['pid']) ? $db->where('pid', $filter['pid']) : '';
		isset($filter['number']) ? $db->where('number', $filter['number']) : '';
		isset($filter['brand_tid']) ? $db->where('brand_tid', $filter['brand_tid']) : '';
		isset($filter['status']) ? $db->where('status', $filter['status']) : '';
		isset($filter['sell_price']) ? $db->where('name >=', $filter['sell_price_low']) : '';
		isset($filter['sell_price']) ? $db->where('name <=', $filter['sell_price_heigh']) : '';
		if (isset($filter['directory_tid']) && $filter['directory_tid']) {
			$taxonomyInstance = Taxonomy_Model::getInstance();
			$termInfo = $taxonomyInstance->getTermInfo($filter['directory_tid']);
			if (isset($termInfo) && $termInfo) {
				if (!$termInfo->ptid1) {
					$filter2['directory_tid1'] = $termInfo->tid;
				} else if (!$termInfo->ptid2) {
					$filter2['directory_tid1'] = $termInfo->ptid1;
					$filter2['directory_tid2'] = $termInfo->tid;
				} else if (!$termInfo->ptid3) {
					$filter2['directory_tid1'] = $termInfo->ptid1;
					$filter2['directory_tid2'] = $termInfo->ptid2;
					$filter2['directory_tid3'] = $termInfo->tid;
				} else {
					$filter2['directory_tid1'] = $termInfo->ptid1;
					$filter2['directory_tid2'] = $termInfo->ptid2;
					$filter2['directory_tid3'] = $termInfo->ptid3;
					$filter2['directory_tid4'] = $termInfo->tid;
				}
			}
		}
		foreach ($filter2 as $key => $value) {
			if (isset($value) && $value !== '' && $value !== false) {
				$db->where($key, $value);
			}
		}
		$db->select('pid,name');
		$result = $db->get('products');
		$list = $result->columnWithKey('pid', 'name');
		return $list;
	}

	/**
	 * 从商品ID列表获取得商品pid和name键值表
	 * Added by 55feng (2010-10-15)
	 * @param Array $pidList, 商品ID列表，数组形式
	 */
	function getProductsNameByPIDList($pidList)
	{
		global $db;
		static $list = array();
		if(!is_array($pidList)||count($pidList)<1){
			return false;
		}

		$pidList = implode(',', $pidList);
		$sql = 'SELECT pid,name FROM products WHERE pid in ('.$db->escape($pidList).')';
		$result = $db->query($sql);
		$list = $result->columnWithKey('pid', 'name');
		return $list;
	}

	/**
	 *
	 * 批量导入商品信息
	 * @param string $type
	 * @param array $productFields
	 * @param array $typeFields
	 * @param array $arr
	 */
	public function batchLeadProduct($type, $set_products, $set_types)
	{
		global $db;
		foreach ($set_products as $key => $set_product) {
			$db->insert('products', $set_product);
			$pid = $db->lastInsertId();
			if ($pid && $set_types) {
				foreach ($set_types[$key] as $key2 => $dll) {
					$set_type[$key2] = $dll;
				}
				$set_type['pid'] = $pid;
				$db->insert('type_'.$type, $set_type);
			}
		}
	}

	public function getSelectHtml($filter, $typeList, $directoryTermsList, $posturl='admin/product/firstList/')
	{
		$selectHtml = '';
		$filter = array_filter($filter, "Common_Model::filterArray");
		if(!empty($filter)) {
			$selectHtml = '<b>'.t('Select Term').'（<a href="'.url('admin/product/firstList/all').'">'.t('Clear Away').'</a>）：</b>';
			foreach ($filter as $key => $dl) {
				if ($dl != '') {
					if ($key =='status') {
						$dl == '1' ? $dl = t('Published') : $dl =  t('Unpublish');
					} else if ($key == 'type') {
						$dl = $typeList[$dl]->name;
					} else if ($key =='tid') {
						$dl = $directoryTermsList[$dl]->name;
					}
					$selectHtml .= $this->getSelectHtmlCeil($key, $dl, $posturl).' ';
				}
			}
		}
		return $selectHtml;
	}

	public function getSelectHtmlCeil($key, $value = "", $posturl)
	{
		switch ($key){
			case 'name' : return '<span>'.t('Product Name').'（'.$value.'）<a href="'.url($posturl.'name').'">×</a></span>';
			case 'sn' : return '<span>'.t('SN').'（'.$value.'）<a href="'.url($posturl.'sn').'">×</a></span>';
			case 'number' : return '<span>'.t('Product Number').'（'.$value.'）<a href="'.url($posturl.'number').'">×</a></span>';
			case 'type' : return '<span>'.t('Product Type').'（'.$value.'）<a href="'.url($posturl.'type').'">×</a></span>';
			case 'tid' : return '<span>'.t('Product Dir').'（'.$value.'）<a href="'.url($posturl.'tid').'">×</a></span>';
			case 'lowprice' : return '<span>'.t('Product Price').'（> '.$value.'）<a href="'.url($posturl.'lowprice').'">×</a></span>';
			case 'highprice' : return '<span>'.t('Product Price').'（< '.$value.'）<a href="'.url($posturl.'highprice').'">×</a></span>';
			case 'status' : return '<span>'.t('Product Status').'（'.$value.'）<a href="'.url($posturl.'status').'">×</a></span>';
		}
	}

	/**
	 * 获取商品会员等级价格
	 * @param int $pid 商品ID
	 * @return array
	 */
	public function getProductRanksPrice($pid)
	{
		global $db;
		static $list;
		if (!isset($list)) {
			$cacheId = 'product-ranks';
			if ($cache = cache::get($cacheId)) {
				$list = $cache->data;
			} else {
				$result = $db->query('SELECT pid, price, rid FROM products_ranks');
				$rows = $result->all();
				$list = array();
				foreach ($rows as $row) {
					if (!isset($list[$row->pid])) {
						$list[$row->pid] = array();
					}
					$list[$row->pid][$row->rid] = $row->price;
				}
				cache::save($cacheId, $list);
			}
		}
		return isset($list[$pid]) ? $list[$pid] : array();
	}

	/**
	 * 根据rid获取商品会员等级价格
	 *
	 * 2010-10-18 Added By 55Feng
	 *
	 * @param int $pid 商品ID
	 * @param int $rid 会员等级ID
	 * @return int
	 */
	public function getProductRanksPriceByRID($pid, $rid)
	{
		global $db;
		$result = $db->query('SELECT price FROM products_ranks WHERE pid="' . $db->escape($pid).'" AND rid="'
		. $db->escape($rid) . '" LIMIT 1');
		return $result->one('price');
	}

	/**
	 * 更新某商品某一会员等级的价格
	 * 2010-11-05 Added By 55Feng
	 */
	public function updateProductRandPriceByRID($pid, $rid, $price)
	{
		global $db;
		$set = array('price' => $price);
		$where = array('pid' => $pid, 'rid' => $rid);
		$db->update('products_ranks', $set, $where);
	}

	/**
	 * 根据rid获取商品促销活动价格
	 *
	 * 2010-10-18 Added By 55Feng
	 *
	 * @param int $pid 商品ID
	 * @param int $rid 会员等级ID
	 * @return int
	 */
	public function getProductPromotionsPriceByRID($pid, $rid)
	{
		global $db;
		$db->where('pp.pid', $pid);
		$db->where('pp.rid', $rid);
		$db->join('promotions p', 'pp.pmid = p.pmid');
		$db->orderby('pp.pmid DESC');
		$db->limit(1);
		$result = $db->get('promotions_products pp');
		return $result->row();
	}

	/**
	 * 新增商品会员等级价格
	 * @param int $pid 商品ID
	 * @param array $post 表单数组
	 */
	public function insertProductRanksPrice($pid, $post)
	{
		global $db;
		foreach ($post as $rid => $price) {
			$db->exec('INSERT INTO products_ranks (pid, rid, price) VALUES (' . $db->escape($pid) . ', ' . $db->escape($rid) . ', ' . $db->escape($price) . ')');
		}
		cache::remove('product-ranks');
	}

	/**
	 * 删除商品会员等级价格
	 * @param int $pid 商品ID
	 * @return boolean
	 */
	public function deleteProductRanksPrice($pid)
	{
		global $db;
		$db->exec('DELETE FROM products_ranks WHERE pid = ' . $db->escape($pid));
		cache::remove('product-ranks');
		return (boolean)$db->affected();
	}

	/**
	 * 获取促销活动列表
	 * @return array
	 */
	public function getPromotionsList()
	{
		global $db;
		static $list = null;
		if (!isset($list)) {
			$result = $db->query('SELECT * FROM promotions ORDER BY weight DESC');
			$list = $result->all();
		}
		return $list;
	}

	/**
	 * 获取促销活动列表, 前台调用
	 * @return array
	 */
	public function getPromotionsListFront($filter = array())
	{
		global $db;
		static $list = null;
		$where = ' WHERE 1=1 ';
		//start_time  end_time  status
		isset($filter['start_time']) ? $where .= ' AND start_time<'.$db->escape($filter['start_time']) : $where .= ' AND start_time<' . time();
		isset($filter['end_time']) ? $where .= ' AND end_time>'.$db->escape($filter['end_time']) : $where .= ' AND end_time>' . time();
		isset($filter['status']) ? $where .= ' AND status='.$db->escape($filter['status']) : $where .= ' AND status=1';
		if (!isset($list)) {
			$result = $db->query('SELECT * FROM promotions '. $where .' ORDER BY weight DESC');
			$list = $result->all();
		}
		return $list;
	}

	/**
	 * 获取促销活动信息
	 * @param int $pmid 活动ID
	 * @return object
	 */
	public function getPromotionInfo($pmid)
	{
		global $db;
		static $list = array();
		if (!isset($list[$pmid])) {
			$result = $db->query('SELECT * FROM promotions WHERE pmid = ' . $db->escape($pmid));
			$list[$pmid] = $result->row();
		}
		return $list[$pmid];
	}

	/**
	 * 根据path_alias获取促销活动id
	 * @param int $pmid 活动ID
	 * @return object
	 */
	public function getPromotionIDByAlias($path)
	{
		global $db;
		$result = $db->query('SELECT pmid FROM promotions WHERE path_alias = "' . $db->escape($path).'"' );
		return $result->one('pmid');
	}

	/**
	 * 获取相同路径别名前缀的列表
	 * @param string $path 路径别名
	 * @return array
	 */
	public function getPromotionPathAliasList($path)
	{
		global $db;
		$result = $db->query('SELECT pmid, path_alias FROM promotions WHERE path_alias LIKE "' . $db->escape($path) . '%"');
		return $result->columnWithKey('pmid', 'path_alias');
	}

	/**
	 * 新建促销活动
	 * @param array $post 表单数组
	 * @return int
	 */
	public function insertPromotion($post)
	{
		global $db;
		if (!isset($post['name'])) {
			return false;
		}
		$set = array(
      'name' => $post['name'],
      'description' => $post['description'],
      'start_time' => $post['start_time'],
      'end_time' => $post['end_time'],
      'status' => isset($post['status']) && $post['status'] ? 1 : 0,
      'path_alias' => $post['path_alias'],
      'template' => $post['template'],
      'visits' => 0,
      'created' => TIMESTAMP,
      'updated' => TIMESTAMP,
      'weight' => isset($post['weight']) ? $post['weight'] : 0,
      'pvid' => isset($post['pvid']) ? $post['pvid'] : 0,
		);
		$db->insert('promotions', $set);
		return $db->lastInsertId();
	}

	/**
	 * 修改促销活动
	 * @param int $pmid 活动ID
	 * @param array $post 菜单数组
	 * @return int
	 */
	public function updatePromotion($pmid, $post)
	{
		global $db;
		if (!isset($post['name'])) {
			return false;
		}
		$set = array(
      'name' => $post['name'],
      'description' => $post['description'],
      'start_time' => $post['start_time'],
      'end_time' => $post['end_time'],
      'status' => isset($post['status']) && $post['status'] ? 1 : 0,
      'path_alias' => $post['path_alias'],
      'template' => $post['template'],
      'visits' => 0,
      'updated' => TIMESTAMP,
      'weight' => isset($post['weight']) ? $post['weight'] : 0,
      'pvid' => isset($post['pvid']) ? $post['pvid'] : 0,
		);
		$db->update('promotions', $set, array('pmid' => $pmid));
		return $db->affected();
	}

	/**
	 * 删除促销活动
	 * @param int $pmid 活动ID
	 * @return int
	 */
	public function deletePromotion($pmid)
	{
		global $db;
		$db->exec('DELETE FROM promotions WHERE pmid = ' . $db->escape($pmid));
		return $db->affected();
	}

	/**
	 * 获得促销活动的商品ID列表
	 * 2010-10-15 Added By 55Feng
	 *
	 * @param int $pmid 活动ID
	 */
	public function getPromotionPidList($pmid)
	{
		global $db;
		$sql = 'SELECT pid FROM promotions_products WHERE pmid = "' . $db->escape($pmid) . '" GROUP BY pid';
		$result = $db->query($sql);
		return $result->column('pid');
	}

	/**
	 * 删除促销活动商品
	 * 2010-10-15 Added By 55Feng
	 *
	 * @param int $pmid 活动ID
	 * @return int
	 */
	public function deletePromotionProduct($pmid, $pid, $rid = 0)
	{
		global $db;
		if($rid!=0){
			$addQuery = ' AND rid="'.$db->escape($rid).'"';
		}
		$db->exec( 'DELETE FROM promotions_products WHERE pmid = "' . $db->escape($pmid) .'" AND pid = "' . $db->escape($pid).'"' . $addQuery );
		return $db->affected();
	}


	/**
	 * 添加促销活动商品
	 * 2010-10-15 Added By 55Feng
	 *
	 * @param int $pmid 活动ID
	 * @param int $pidList 要添加的促销活动商品ID列表
	 * @return int
	 */
	public function addPromotionProduct($pmid, $pidList)
	{
		global $db;
		if (!is_array($pidList)||count($pidList)<1) {
			return false;
		}
		$pidList = implode(',', $pidList);

		// rid 为 RANK_MEMBER 的，全部保存
		$sql1 = 'INSERT INTO promotions_products(pmid,pid,rid,price) SELECT '.$db->escape($pmid).',pid,'
		.User_Model::RANK_MEMBER.',sell_price FROM products WHERE pid in ('.$db->escape($pidList).');';

		// rid 为其它的，只保存原来商品编辑时指定过会员价格的
		$sql2 = 'INSERT INTO promotions_products(pmid,pid,rid,price) SELECT '.$db->escape($pmid).',pid,'
		.'rid,price FROM products_ranks WHERE pid in ('.$db->escape($pidList).');';

		$db->exec($sql1);
		$db->exec($sql2);

		return true;
	}

	/**
	 * 更新促销活动的商品价格
	 * 2010-10-15 Added By 55Feng
	 *
	 * @param $pmid
	 * @param $pid
	 * @param $rid
	 * @param $price
	 */
	public function updatePromotionPrice($pmid, $pid, $rid, $price)
	{
		global $db;
		$table = 'promotions_products';
		$set = array(
             'price'=>$price,
		);
		$where = array(
             'pmid'=>$pmid,
             'pid'=>$pid,
             'rid'=>$rid,
		);
		$db->update($table, $set, $where);
	}

	/**
	 * 向促销活动加入新的商品
	 * 2010-10-15 Added By 55Feng
	 *
	 * @param $pmid
	 * @param $pid
	 * @param $rid
	 * @param $price
	 */
	public function insertPromotionPrice($pmid, $pid, $rid, $price)
	{
		global $db;
		$table = 'promotions_products';
		$set = array(
             'pmid'=>$pmid,
             'pid'=>$pid,
             'rid'=>$rid,
             'price'=>$price,
		);
		$db->insert($table, $set);
	}

	/**
	 * 从会员等级ID获得商品列表
	 * @param int $pmid
	 * @param int $rid
	 */
	public function getPromotionProductsByRID($pmid, $rid)
	{
		global $db;
		$sql = 'SELECT pm.*,p.name FROM promotions_products pm, products p WHERE pm.pmid= "'
		.$db->escape($pmid).'" AND pm.rid= "'.$db->escape($rid).'" AND p.pid=pm.pid  ';

		$result = $db->query($sql);
		return $result->allWithKey('pid');

	}


	/**
	 * 获取相似商品的 number
	 * @param unknown_type $name
	 */
	public function getsimilarProductNumber($name)
	{
		global $db;
		$db->select('name');
		$db->where('name like', $name.'%');
		$db->orderby('pid DESC');
		$result = $db->get('products');
		$number = $result->one();
		return $number;
	}

	/**
	 * 更新商品卖出的情况
	 * @param $pid
	 * @param $num
	 */
	public function updateTransactions($pid, $num)
	{
		global $db;
		$db->update('products', array(
      'transactions' => array(
        'escape' => false,
        'value' => 'transactions + ' . $num,
		)), array('pid' => $pid));
	}

	/**
	 * 结算时检查商品最小数和最大数
	 * @param $post
	 */
	public function checkQtyOfCommodiy($post)
	{
		global $db;
		$str = '';
		$status = true;
		if (is_array($post['pids'])) {
			foreach ($post['pids'] as $k => $v) {
				$str .= $this->checkQtyOfCommodiy_($v, $post['qtys'][$k]);
			}
		}
		if ($str) {
			$status = false;
		}
		return array($status, $str);
	}

	/**
	 * 结算时检查商品最小数和最大数 -- 单个
	 * @param $post
	 */
	public function checkQtyOfCommodiy_($pid, $qty)
	{
		global $db;
		$str = '';
		$db->where('pid', $pid);
		$result = $db->get('products');
		$row = $result->row();
		if (isset($row->sell_min) && $row->sell_min > 0 && $qty < $row->sell_min) {
			$str = $row->name . t('Less than the minimum purchase');
		} else if (isset($row->sell_max) && $row->sell_max > 0 && $qty > $row->sell_max) {
			$str = $row->name . t('Larger than the maximum number of purchase');
		}
		return $str;
	}

	public function searchProductList($filter, $page = 1, $pageRows = 60)
	{
		global $db;
		// TODO
		if (!$pageRows) {
			$pageRows = 60;
		}
		$db->select('p.*');

		if (isset($filter['directory_tid']) && $filter['directory_tid']) {
			$taxonomyInstance = Taxonomy_Model::getInstance();
			$termInfo = $taxonomyInstance->getTermInfo($filter['directory_tid']);
			if (isset($termInfo) && $termInfo) {
				if (!$termInfo->ptid1) {
					$filter2['directory_tid1'] = $termInfo->tid;
				} else if (!$termInfo->ptid2) {
					$filter2['directory_tid1'] = $termInfo->ptid1;
					$filter2['directory_tid2'] = $termInfo->tid;
				} else if (!$termInfo->ptid3) {
					$filter2['directory_tid1'] = $termInfo->ptid1;
					$filter2['directory_tid2'] = $termInfo->ptid2;
					$filter2['directory_tid3'] = $termInfo->tid;
				} else {
					$filter2['directory_tid1'] = $termInfo->ptid1;
					$filter2['directory_tid2'] = $termInfo->ptid2;
					$filter2['directory_tid3'] = $termInfo->ptid3;
					$filter2['directory_tid4'] = $termInfo->tid;
				}
			}
		}
		if (isset($filter2) && $filter2) {
			foreach ($filter2 as $key => $value) {
				if (isset($value) && $value !== '' && $value !== false) {
					$db->where($key, $value);
				}
			}
		}

		if (isset($filter['brand_tid']) && $filter['brand_tid']) {
			$db->where('p.brand_tid', $filter['brand_tid']);
		}
		if (isset($filter['tids']) && $filter['tids']) {
			foreach ($filter['tids'] as $k => $tid) {
				$result = $db->query('SELECT pid FROM terms_products tp WHERE tp.tid = ' . $tid);
				$pids = $result->column('pid');
				$pids = isset($pids) && $pids ? $pids : array(0);
				$db->where('p.pid IN', $pids);
			}
		}
		if (isset($filter['type']) && $filter['type']) {
			$db->where('p.type', $filter['type']);
			if (isset($filter['filedsarr']) && $filter['filedsarr']) {
				foreach ($filter['filedsarr'] as $k => $v) {
					$result2 = $db->query("SELECT multiple FROM products_type_fields WHERE type = '".$filter['type']."' and field_name = '".$k."'");
					$multiple = $result2->one();
					if ($multiple == 1) {
						$tb = $this->getTypeFieldTableName($filter['type'], $k);
					} else {
						$tb = $this->getTypeTableName($filter['type']);
					}
					$db->join($tb, $tb.'.pid = p.pid');
					$db->where($tb.'.field_' . $k, $v);
				}
			}
		}
		if (isset($filter['pricesarr'][0]) && $filter['pricesarr'][0]) {
			$db->where('p.sell_price >=', $filter['pricesarr'][0]);
		}
		if (isset($filter['pricesarr'][1]) && $filter['pricesarr'][1]) {
			$db->where('p.sell_price <', $filter['pricesarr'][1]);
		}

		$router = Bl_Core::getRouter();
		$undercarriageShow = Bl_Config::get('undercarriageShow');
		if ($router['folder'] != 'admin') {
			$db->where('p.status >=', 0);
			if (!isset($undercarriageShow) || !$undercarriageShow) {
				$db->where('p.status', 1);
			}
			$noStockHidden = Bl_Config::get('noStockHidden');
			if(isset($noStockHidden) && $noStockHidden){
				$db->where('p.stock >', 0);
			}
		}
		if (isset($filter['orderby']) && $filter['orderby']) {
			$db->orderby($filter['orderby']);
		} else {
			$db->orderby('p.weight DESC, p.pid DESC');
		}
		if($pageRows && $pageRows != 'all'){
			$db->limitPage($pageRows, $page);
		}
		$result = $db->get('products p');
		//$pids = $result->column('pid');
		$productList = $result->allWithKey('pid');
		foreach($productList as $pid => $product){
			$product->url = ($product->path_alias !== '' ? $product->path_alias : $product->pid).'-p'.$product->sn . '.html';
			$product->price = $product->sell_price;
			//TODO enable when there were promotion or customer value info added. Now omit this.
			/*
			if (isset($product) && $product) {
			$this->getProductRealPrice($product);
			}*/
		}

		/*
		 foreach ($pids as $pid) {
		 $productList[$pid] = $this->getProductInfo($pid);
		 $productList[$pid]->related = $this->listProductRelated($pid, 1, 5);
		 }*/
		return $productList;
	}

	public function searchProductCount($filter)
	{

		global $db;
		$db->select('COUNT(0)');

		if (isset($filter['directory_tid']) && $filter['directory_tid']) {
			$taxonomyInstance = Taxonomy_Model::getInstance();
			$termInfo = $taxonomyInstance->getTermInfo($filter['directory_tid']);
			if (isset($termInfo) && $termInfo) {
				if (!$termInfo->ptid1) {
					$filter2['directory_tid1'] = $termInfo->tid;
				} else if (!$termInfo->ptid2) {
					$filter2['directory_tid1'] = $termInfo->ptid1;
					$filter2['directory_tid2'] = $termInfo->tid;
				} else if (!$termInfo->ptid3) {
					$filter2['directory_tid1'] = $termInfo->ptid1;
					$filter2['directory_tid2'] = $termInfo->ptid2;
					$filter2['directory_tid3'] = $termInfo->tid;
				} else {
					$filter2['directory_tid1'] = $termInfo->ptid1;
					$filter2['directory_tid2'] = $termInfo->ptid2;
					$filter2['directory_tid3'] = $termInfo->ptid3;
					$filter2['directory_tid4'] = $termInfo->tid;
				}
			}
		}
		if (isset($filter2) && $filter2) {
			foreach ($filter2 as $key => $value) {
				if (isset($value) && $value !== '' && $value !== false) {
					$db->where($key, $value);
				}
			}
		}

		if (isset($filter['brand_tid']) && $filter['brand_tid']) {
			$db->where('p.brand_tid', $filter['brand_tid']);
		}
		if (isset($filter['tids']) && $filter['tids']) {
			foreach ($filter['tids'] as $k => $tid) {
				$result = $db->query('SELECT pid FROM terms_products tp WHERE tp.tid = ' . $tid);
				$pids = $result->column('pid');
				$pids = isset($pids) && $pids ? $pids : array(0);
				$db->where('p.pid IN', $pids);
			}
		}
		$router = Bl_Core::getRouter();
		$undercarriageShow = Bl_Config::get('undercarriageShow');
		if ($router['folder'] != 'admin') {
			$db->where('p.status >=', 0);
			if (!isset($undercarriageShow) || !$undercarriageShow) {
				$db->where('p.status', 1);
			}
			$noStockHidden = Bl_Config::get('noStockHidden');
			if(isset($noStockHidden) && $noStockHidden){
				$db->where('p.stock >', 0);
			}
		}
		if (isset($filter['type']) && $filter['type']) {
			$db->where('p.type', $filter['type']);
			if (isset($filter['filedsarr']) && $filter['filedsarr']) {
				foreach ($filter['filedsarr'] as $k => $v) {
					$result2 = $db->query("SELECT multiple FROM products_type_fields WHERE type = '".$filter['type']."' and field_name = '".$k."'");
					$multiple = $result2->one();
					if ($multiple == 1) {
						$tb = $this->getTypeFieldTableName($filter['type'], $k);
					} else {
						$tb = $this->getTypeTableName($filter['type']);
					}
					$db->join($tb, $tb.'.pid = p.pid');
					$db->where($tb.'.field_' . $k, $v);
				}
			}
		}
		if (isset($filter['pricesarr'][0]) && $filter['pricesarr'][0]) {
			$db->where('p.sell_price >=', $filter['pricesarr'][0]);
		}
		if (isset($filter['pricesarr'][1]) && $filter['pricesarr'][1]) {
			$db->where('p.sell_price <=', $filter['pricesarr'][1]);
		}
		if (isset($filter['orderby']) && $filter['orderby']) {
			$db->orderby($filter['orderby']);
		} else {
			$db->orderby('p.weight DESC, p.pid DESC');
		}
		$result = $db->get('products p');
		return $result->one();
	}

	/**
	 * 获取商品最大价格，最小价格
	 * @param unknown_type $post
	 */
	public function getHighAndLowPrice($post = array())
	{
		global $db;
		$db->select('MAX(sell_price) high_price, MIN(sell_price) low_price');

		if (isset($post['directory_tid']) && $post['directory_tid']) {
			$taxonomyInstance = Taxonomy_Model::getInstance();
			$termInfo = $taxonomyInstance->getTermInfo($post['directory_tid']);
			if (isset($termInfo) && $termInfo) {
				if (!$termInfo->ptid1) {
					$filter2['directory_tid1'] = $termInfo->tid;
				} else if (!$termInfo->ptid2) {
					$filter2['directory_tid1'] = $termInfo->ptid1;
					$filter2['directory_tid2'] = $termInfo->tid;
				} else if (!$termInfo->ptid3) {
					$filter2['directory_tid1'] = $termInfo->ptid1;
					$filter2['directory_tid2'] = $termInfo->ptid2;
					$filter2['directory_tid3'] = $termInfo->tid;
				} else {
					$filter2['directory_tid1'] = $termInfo->ptid1;
					$filter2['directory_tid2'] = $termInfo->ptid2;
					$filter2['directory_tid3'] = $termInfo->ptid3;
					$filter2['directory_tid4'] = $termInfo->tid;
				}
			}
		}
		if (isset($filter2) && $filter2) {
			foreach ($filter2 as $key => $value) {
				if (isset($value) && $value !== '' && $value !== false) {
					$db->where($key, $value);
				}
			}
		}

		if (isset($post['brand_tid']) && $post['brand_tid']) {
			$db->where('p.brand_tid', $post['brand_tid']);
		}
		if (isset($post['tids']) && $post['tids']) {
			foreach ($post['tids'] as $k => $tid) {
				$result = $db->query('SELECT pid FROM terms_products tp WHERE tp.tid = ' . $tid);
				$pids = $result->column('pid');
				if (isset($pids) && !empty($pids)) {
					$db->where('p.pid IN', $pids);
				}
			}
		}
		$result = $db->get('products p');
		return $result->row();
	}

	public function getProductFieldsIndex($type)
	{
		global $db;
		$db->where('indexed', 1);
		$db->where('type', $type);
		$result = $db->get('products_type_fields');
		$fields = $result->all();
		foreach ($fields as $field) {
			if ($field->multiple == 1) {
				$tb = $this->getTypeFieldTableName($type, $field->field_name);
			} else {
				$tb = $this->getTypeTableName($type);
			}
			$fieldName = $this->getTypeFieldName($field->field_name);
			$db->select('COUNT(0) num, `' . $fieldName . '` value');
			$db->groupby($fieldName);
			$db->orderby('num DESC');
			$result = $db->get($tb);
			$field->attribute = $result->all();
		}
		return $fields;
	}

	/**
	 * 获取商品类型列表(通过商品)
	 * @return array
	 */
	public function getTypeListByProduct($post)
	{
		global $db;
		$db->select('DISTINCT(p.type), pt.name');

		if (isset($post['directory_tid']) && $post['directory_tid']) {
			$taxonomyInstance = Taxonomy_Model::getInstance();
			$termInfo = $taxonomyInstance->getTermInfo($post['directory_tid']);
			if (isset($termInfo) && $termInfo) {
				if (!$termInfo->ptid1) {
					$filter2['p.directory_tid1'] = $termInfo->tid;
				} else if (!$termInfo->ptid2) {
					$filter2['p.directory_tid1'] = $termInfo->ptid1;
					$filter2['p.directory_tid2'] = $termInfo->tid;
				} else if (!$termInfo->ptid3) {
					$filter2['p.directory_tid1'] = $termInfo->ptid1;
					$filter2['p.directory_tid2'] = $termInfo->ptid2;
					$filter2['p.directory_tid3'] = $termInfo->tid;
				} else {
					$filter2['p.directory_tid1'] = $termInfo->ptid1;
					$filter2['p.directory_tid2'] = $termInfo->ptid2;
					$filter2['p.directory_tid3'] = $termInfo->ptid3;
					$filter2['p.directory_tid4'] = $termInfo->tid;
				}
			}
		}
		if (isset($filter2) && $filter2) {
			foreach ($filter2 as $key => $value) {
				if (isset($value) && $value !== '' && $value !== false) {
					$db->where($key, $value);
				}
			}
		}
		if (isset($post['brand_tid']) && $post['brand_tid']) {
			$db->where('p.brand_tid', $post['brand_tid']);
		}
		if (isset($post['tids']) && $post['tids']) {
			foreach ($post['tids'] as $k => $tid) {
				$result = $db->query('SELECT pid FROM terms_products tp WHERE tp.tid = ' . $tid);
				$pids = $result->column('pid');
				if (isset($pids) && !empty($pids)) {
					$db->where('p.pid IN', $pids);
				}
			}
		}
		$router = Bl_Core::getRouter();
		$undercarriageShow = Bl_Config::get('undercarriageShow');
		if ($router['folder'] != 'admin') {
			$db->where('p.status >=', 0);
			if (!isset($undercarriageShow) || !$undercarriageShow) {
				$db->where('p.status', 1);
			}
			$noStockHidden = Bl_Config::get('noStockHidden');
			if(isset($noStockHidden) && $noStockHidden){
				$db->where('p.stock >', 0);
			}
		}
		$db->join('products_type pt', 'pt.type = p.type');
		$result = $db->get('products p');
		return $result->all();
	}

	/**
	 *
	 */
	public function getProductTags($pid){
		global $db;
		$db->where('tp.pid', $pid);
		$router = Bl_Core::getRouter();
		if ($router['folder'] != 'admin') {
			$db->where('t.visible', 1);
		}
		$db->where('t.vid', Taxonomy_Model::TYPE_TAG);
		$db->join('terms_products tp', 't.tid = tp.tid');
		$result = $db->get('terms t');
		return $result->all();
	}

	public function getProductIdByFile($filename) {
		global $db;
		$db->select('pf.pid');
		$db->join('products_files pf', 'f.fid = pf.fid');
		$db->where('f.filename REGEXP', $filename);
		$db->orderby('f.fid DESC');
		$db->limit(1);
		$result = $db->get('files f');
		return $result->one();
	}

	/**
	 * 获取上一个下一个的商品信息
	 */
	public function getClosesProducts($pid, $post = array())
	{
		global $db;
		$db->select('pid');
		foreach ($post as $k => $v) {
			$db->where($k, $v);
		}
		$db->where('pid > ', $pid);
		$result = $db->get('products');
		$nextAid = $result->one();
		if (isset($nextAid) && $nextAid) {
			$closesProducts->next = $this->getProductInfo($nextAid);
		}

		$db->select('pid');
		foreach ($post as $k => $v) {
			$db->where($k, $v);
		}
		$db->where('pid < ', $pid);
		$db->orderby('pid DESC');
		$result = $db->get('products');
		$prevAid = $result->one();
		if (isset($prevAid) && $prevAid) {
			$closesProducts->prev = $this->getProductInfo($prevAid);
		}

		return isset($closesProducts) ? $closesProducts : null;
	}


	/**
	 * SELECT `terms`.`tid`, `products`.pid, `products`.name, `products`.filepath,`products`.path_alias, `comments`.*
	 FROM `terms`
	 INNER JOIN `products`
	 ON `terms`.`tid` = `products`.`directory_tid1` OR `terms`.`tid` = `products`.`directory_tid2` OR `terms`.`tid` = `products`.`directory_tid1`
	 INNER JOIN `products_comments`
	 ON `products`.`pid` = `products_comments`.`pid`
	 INNER JOIN `comments`
	 ON `products_comments`.`cid` = `comments`.`cid`
	 where `tid` = 47;
	 */
	public function getPopularCategoryComments($tid, $order = 'timestamp desc', $commentCount = 5){
		global $db;
		$db->select('terms.tid, products.pid, products.name, products.filepath,products.path_alias, comments.*');
		$db->from('terms');
		$db->join('products', 'terms.tid = products.directory_tid1 or terms.tid = products.directory_tid2 or terms.tid = products.directory_tid3');
		$db->join('products_comments', 'products.pid = products_comments.pid');
		$db->join('comments', 'products_comments.cid = comments.cid');
		$db->where('tid = ', $tid);
		$db->orderby($order);
		$db->limit($commentCount);
		$result = $db->get();
		$categoryComments = $result->all();
		return $categoryComments;
	}

	public function getPopularCommentedProducts($tid, $limit = -1){
		global $db;
		$db->select('terms.tid, products.pid, count(comments.cid) as comments_count, products.name, products.list_price, products.sell_price, products.filepath,products.path_alias');
		$db->from('terms');
		$db->join('products', 'terms.tid = products.directory_tid1 or terms.tid = products.directory_tid2 or terms.tid = products.directory_tid3');
		$db->join('products_comments', 'products.pid = products_comments.pid');
		$db->join('comments', 'products_comments.cid = comments.cid');
		$db->where('tid = ', $tid);
		$db->where('comments.status = ', 1);
		$db->groupby('products.pid');
		$db->orderby('products.pid DESC');
		if($limit !== -1){
			$db->limit($limit);
		}
		$result = $db->get();
		$popularCommentedProducts = $result->all();
		return $popularCommentedProducts;
	}

	public function getPopularCommentedProductsByPage($tid, $page = 1, $pageRows = 15){
		global $db;
		$db->select('terms.tid, products.pid, count(comments.cid) as comments_count, products.name, products.list_price, products.sell_price, products.filepath,products.path_alias');
		$db->from('terms');
		$db->join('products', 'terms.tid = products.directory_tid1 or terms.tid = products.directory_tid2 or terms.tid = products.directory_tid3');
		$db->join('products_comments', 'products.pid = products_comments.pid');
		$db->join('comments', 'products_comments.cid = comments.cid');
		$db->where('tid = ', $tid);
		$db->where('comments.status = ', 1);
		$db->groupby('products.pid');
		$db->orderby('products.pid DESC');

		if($pageRows && $pageRows != 'all'){
			$db->limitPage($pageRows, $page);
		}
		$result = $db->get();
		$popularCommentedProducts = $result->all();
		return $popularCommentedProducts;
	}


}
