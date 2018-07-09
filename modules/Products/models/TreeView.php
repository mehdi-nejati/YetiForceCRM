<?php

/**
 * Products TreeView Model Class.
 *
 * @copyright YetiForce Sp. z o.o
 * @license YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */
class Products_TreeView_Model extends Vtiger_TreeView_Model
{
	public function isActive()
	{
		return true;
	}

	private function getRecords()
	{
		$pagingModel = new Vtiger_Paging_Model();
		$pagingModel->set('limit', 0);
		$listViewModel = Vtiger_ListView_Model::getInstance($this->getModuleName());
		$listEntries = $listViewModel->getListViewEntries($pagingModel);
		$tree = [];
		foreach ($listEntries as $item) {
			++$this->lastTreeId;
			$parent = $item->get('pscategory');
			$parent = (int) str_replace('T', '', $parent);
			$tree[] = [
				'id' => $this->lastTreeId,
				'type' => 'record',
				'record_id' => $item->getId(),
				'parent' => $parent == 0 ? '#' : $parent,
				'text' => $item->getName(),
				'isrecord' => true,
				'state' => [],
				'icon' => 'fas fa-file',
			];
		}
		return $tree;
	}

	/**
	 * Load tree.
	 *
	 * @return string
	 */
	public function getTreeList()
	{
		$tree = parent::getTreeList();
		$treeWithItems = $this->getRecords();
		$tree = array_merge($tree, $treeWithItems);

		return $tree;
	}
}
