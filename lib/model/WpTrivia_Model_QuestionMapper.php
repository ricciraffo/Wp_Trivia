<?php

class WpTrivia_Model_QuestionMapper extends WpTrivia_Model_Mapper
{
    private $_table;

    public function __construct()
    {
        parent::__construct();

        $this->_table = $this->_prefix . "question";
    }

    public function delete($id)
    {
        $this->_wpdb->delete($this->_table, array('id' => $id), '%d');
    }

    public function deleteByQuizId($id)
    {
        $this->_wpdb->delete($this->_table, array('quiz_id' => $id), '%d');
    }

    public function updateSort($id, $sort)
    {
        $this->_wpdb->update(
            $this->_table,
            array(
                'sort' => $sort
            ),
            array('id' => $id),
            array('%d'),
            array('%d'));
    }

    public function setOnlineOff($questionId)
    {
        return $this->_wpdb->update($this->_tableQuestion, array('online' => 0), array('id' => $questionId), null,
            array('%d'));
    }

    public function getQuizId($questionId)
    {
        return $this->_wpdb->get_var($this->_wpdb->prepare("SELECT quiz_id FROM {$this->_tableQuestion} WHERE id = %d",
            $questionId));
    }

    public function getMaxSort($quizId)
    {
        return $this->_wpdb->get_var($this->_wpdb->prepare(
            "SELECT MAX(sort) AS max_sort FROM {$this->_tableQuestion} WHERE quiz_id = %d AND online = 1", $quizId));
    }

    public function getSortByQuestionId($questionId)
    {
        return $this->_wpdb->get_var($this->_wpdb->prepare("SELECT sort FROM {$this->_tableQuestion} WHERE id = %d",
            $questionId));
    }

    public function save(WpTrivia_Model_Question $question, $auto = false)
    {
        $sort = null;

        if ($auto && $question->getId()) {
            $statisticMapper = new WpTrivia_Model_StatisticMapper();

            if ($statisticMapper->isStatisticByQuestionId($question->getId())) {
                $this->setOnlineOff($question->getId());
                $question->setQuizId($this->getQuizId($question->getId()));
                $sort = $this->getSortByQuestionId($question->getId());
                $question->setId(0);
            }
        }

        if ($question->getId() != 0) {
            $this->_wpdb->update(
                $this->_table,
                array(
                    'title' => $question->getTitle(),
                    'image_id' => $question->getImageId(),
                    'points' => $question->getPoints(),
                    'question' => $question->getQuestion(),
                    'correct_msg' => $question->getCorrectMsg(),
                    'incorrect_msg' => $question->getIncorrectMsg(),
                    'correct_same_text' => (int)$question->isCorrectSameText(),
                    'tip_enabled' => (int)$question->isTipEnabled(),
                    'tip_msg' => $question->getTipMsg(),
                    'answer_type' => $question->getAnswerType(),
                    'show_points_in_box' => (int)$question->isShowPointsInBox(),
                    'answer_points_activated' => (int)$question->isAnswerPointsActivated(),
                    'answer_data' => $question->getAnswerData(true),
                    'answer_points_diff_modus_activated' => (int)$question->isAnswerPointsDiffModusActivated(),
                    'disable_correct' => (int)$question->isDisableCorrect()
                ),
                array('id' => $question->getId()),
                array('%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d'),
                array('%d'));
        } else {
            $this->_wpdb->insert($this->_table, array(
                'quiz_id' => $question->getQuizId(),
                'online' => 1,
                'sort' => $sort !== null ? $sort : ($this->getMaxSort($question->getQuizId()) + 1),
                'title' => $question->getTitle(),
                'image_id' => $question->getImageId(),
                'points' => $question->getPoints(),
                'question' => $question->getQuestion(),
                'correct_msg' => $question->getCorrectMsg(),
                'incorrect_msg' => $question->getIncorrectMsg(),
                'correct_same_text' => (int)$question->isCorrectSameText(),
                'tip_enabled' => (int)$question->isTipEnabled(),
                'tip_msg' => $question->getTipMsg(),
                'answer_type' => $question->getAnswerType(),
                'show_points_in_box' => (int)$question->isShowPointsInBox(),
                'answer_points_activated' => (int)$question->isAnswerPointsActivated(),
                'answer_data' => $question->getAnswerData(true),
                'answer_points_diff_modus_activated' => (int)$question->isAnswerPointsDiffModusActivated(),
                'disable_correct' => (int)$question->isDisableCorrect()
            ),
                array(
                    '%d',
                    '%d',
                    '%d',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%d',
                    '%s',
                    '%s',
                    '%d',
                    '%d',
                    '%s',
                    '%d',
                    '%d',
                    '%d'
                )
            );

            $question->setId($this->_wpdb->insert_id);
        }

        return $question;
    }

    public function fetch($id)
    {

        $row = $this->_wpdb->get_row(
            $this->_wpdb->prepare(
                "SELECT
					*
				FROM
					" . $this->_table . "
				WHERE
					id = %d AND online = 1",
                $id),
            ARRAY_A
        );

        $model = new WpTrivia_Model_Question($row);

        return $model;
    }

    /**
     * TODO
     * @param  {int} $id | current question id
     * @return {WpTrivia_Model_Question || null} $model | Next question or null (quiz ended)
     */
    public function fetchNext($id) {

        $currentQuestion = $this->_wpdb->get_row(
            $this->_wpdb->prepare(
                "
                SELECT  quiz_id, sort
                FROM    " . $this->_table . "
                WHERE   id = %d AND online = 1
                ",
                $id
            ),
            ARRAY_A
        );

        $nextQuestion = $this->_wpdb->get_row(
            $this->_wpdb->prepare(
                "
                SELECT  *
                FROM    " . $this->_table . "
                WHERE   quiz_id = %d AND sort = %d AND online = 1
                ",
                $currentQuestion['quiz_id'],
                $currentQuestion['sort'] + 1
            ),
            ARRAY_A
        );

        if (!$nextQuestion) {
            return null;
        }

        return new WpTrivia_Model_Question($nextQuestion);
    }

    /**
     * @param $id
     * @return WpTrivia_Model_Question|WpTrivia_Model_Question[]|null
     */
    public function fetchById($id)
    {

        $ids = array_map('intval', (array)$id);
        $a = array();

        if (empty($ids)) {
            return null;
        }

        $results = $this->_wpdb->get_results(
            "SELECT
					*
				FROM
					" . $this->_table . "
				WHERE
					id IN(" . implode(', ', $ids) . ") AND online = 1",
            ARRAY_A
        );

        foreach ($results as $row) {
            $a[] = new WpTrivia_Model_Question($row);

        }

        return is_array($id) ? $a : (isset($a[0]) ? $a[0] : null);
    }

    /**
     * @param $quizId
     * @param bool $rand
     * @param int $max
     *
     * @return WpTrivia_Model_Question[]
     */
    public function fetchAll($quizId, $rand = false, $max = 0)
    {

        if ($rand) {
            $orderBy = 'ORDER BY RAND()';
        } else {
            $orderBy = 'ORDER BY sort ASC';
        }

        $limit = '';

        if ($max > 0) {
            $limit = 'LIMIT 0, ' . ((int)$max);
        }

        $a = array();
        $results = $this->_wpdb->get_results(
            $this->_wpdb->prepare(
                'SELECT
								q.*
							FROM
								' . $this->_table . ' AS q
							WHERE
								quiz_id = %d AND q.online = 1
							' . $orderBy . '
							' . $limit
                , $quizId),
            ARRAY_A);

        foreach ($results as $row) {
            $model = new WpTrivia_Model_Question($row);

            $a[] = $model;
        }

        return $a;
    }

    /**
     * @param $quizId
     * @param $orderBy
     * @param $order
     * @param $search
     * @param $limit
     * @param $offset
     * @param $filter
     * @return array
     */
    public function fetchTable($quizId, $orderBy, $order, $search, $limit, $offset, $filter)
    {
        $r = array();

        switch ($orderBy) {
            case 'name':
                $_orderBy = 'q.title';
                break;
            default:
                $_orderBy = 'q.sort';
                $order = 'asc';
                break;
        }

        $whereFilter = '';

        $results = $this->_wpdb->get_results($this->_wpdb->prepare("
				SELECT
					q.*
				FROM
					{$this->_table} AS q
				WHERE
					quiz_id = %d AND q.online = 1 AND
					q.title LIKE %s
					{$whereFilter}
				ORDER BY
					{$_orderBy} " . ($order == 'asc' ? 'asc' : 'desc') . "
				LIMIT %d, %d
			", array(
            $quizId,
            '%' . $search . '%',
            $offset,
            $limit
        )), ARRAY_A);

        foreach ($results as $row) {
            $r[] = new WpTrivia_Model_Question($row);
        }

        $count = $this->_wpdb->get_var($this->_wpdb->prepare("
				SELECT
					COUNT(*) as count_rows
				FROM
					{$this->_table} AS q
				WHERE
					quiz_id = %d AND q.online = 1 AND
					q.title LIKE %s
					{$whereFilter}
			", array(
            $quizId,
            '%' . $search . '%'
        )));

        return array(
            'questions' => $r,
            'count' => $count ? $count : 0
        );
    }

    public function fetchAllList($quizId, $list, $sort = false)
    {
        $sort = $sort ? 'ORDER BY sort' : '';

        $results = $this->_wpdb->get_results(
            $this->_wpdb->prepare(
                'SELECT
								' . implode(', ', (array)$list) . '
							FROM
								' . $this->_tableQuestion . '
							WHERE
								quiz_id = %d AND online = 1
							' . $sort
                , $quizId),
            ARRAY_A);

        return $results;
    }

    public function count($quizId)
    {
        return $this->_wpdb->get_var($this->_wpdb->prepare("SELECT COUNT(*) FROM {$this->_table} WHERE quiz_id = %d AND online = 1",
            $quizId));
    }

    public function exists($id)
    {
        return $this->_wpdb->get_var($this->_wpdb->prepare("SELECT COUNT(*) FROM {$this->_table} WHERE id = %d AND online = 1",
            $id));
    }

    public function existsAndWritable($id)
    {
        return $this->_wpdb->get_var($this->_wpdb->prepare("SELECT COUNT(*) FROM {$this->_table} WHERE id = %d AND online = 1",
            $id));
    }
}
