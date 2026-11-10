<?php

namespace app\admin\controller;

use app\common\model\AdPlanUserModel;
use app\common\model\AdPlanModel;

/**
 * Ad Plan User Management
 */
class AdPlanUser extends Base
{
  protected $model = null;
  protected $searchFields = 'user.username,user.nickname,user.mobile';

  public function initialize()
  {
    parent::initialize();
    $this->model = new AdPlanUserModel();
  }

  /**
   * View list of users with ad plans
   */
  public function index()
  {
    // Get all ad plans for search dropdown
    $adPlans = AdPlanModel::where('status', 1)->select();
    if ($this->request->isAjax()) {
      // Get parameters
      $page = $this->request->get('page/d', 1);
      $limit = $this->request->get('limit/d', 10);
      $search = $this->request->get('search', '');
      $where = [];

      // Handle search parameters
      $id = $this->request->get('id', '');
      $user_name = $this->request->get('user_name', '');
      $ad_plan_id = $this->request->get('ad_plan_id', '');
      $day = $this->request->get('day', '');
      $next_id = $this->request->get('next_id', '');

      // Add specific search conditions if provided
      if ($id !== '') {
        $where[] = ['u.id', '=', $id];
      }

      if ($user_name !== '') {
        $where[] = ['u.user_name', 'LIKE', "{$user_name}%"];
      }

      if ($ad_plan_id !== '') {
        $where[] = ['u.ad_plan_id', '=', $ad_plan_id];
      } else {
          $where[] = ['u.ad_plan_id', '>', 0];
      }

      if ($next_id !== '') {
        // Changed to query ad_plan.next_id instead of apu.next_id
        $where[] = ['ap.next_id', '', $next_id];
      }

      // Handle general search if provided
      if (!empty($search)) {
        $whereOr = [];
        $searchFields = explode(',', $this->searchFields);
        foreach ($searchFields as $field) {
          $whereOr[] = [$field, 'LIKE', "%{$search}%"];
        }
        if (!empty($whereOr)) {
          $where[] = function ($query) use ($whereOr) {
            $query->whereOr($whereOr);
          };
        }
      }

      // Get data
      $result = AdPlanUserModel::getUsersWithAdPlans($where, $page, $limit, $day);
      return json(msg(1, $result, ''));
    }

      return view('index', [
          'adPlans' => $adPlans,
      ]);
  }
}
