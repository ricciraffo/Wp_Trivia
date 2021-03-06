<?php

class WpTrivia_Controller_Front
{

    /**
     * @var WpTrivia_Model_GlobalSettings
     */
    private $_settings = null;

    public function __construct()
    {
        $this->loadSettings();

        add_action('wp_enqueue_scripts', array($this, 'loadDefaultScripts'));
        add_shortcode('WpTrivia', array($this, 'shortcode'));
        add_shortcode('WpTrivia_toplist', array($this, 'shortcodeToplist'));
    }

    public function loadDefaultScripts()
    {
        wp_enqueue_script('jquery');

        wp_enqueue_script('wpTrivia_front_slick', plugins_url('js/slick/slick.min.js', WPPROQUIZ_FILE), array('jquery'));
        wp_enqueue_style('wpTrivia_front_slick', plugins_url('js/slick/slick.css', WPPROQUIZ_FILE));

        $data = array(
            'src' => plugins_url('css/wpTrivia_front' . (WPPROQUIZ_DEV ? '' : '.min') . '.css', WPPROQUIZ_FILE),
            'deps' => array(),
            'ver' => WPPROQUIZ_VERSION,
        );

        $data = apply_filters('wpTrivia_front_style', $data);

        wp_enqueue_style('wpTrivia_front_style', $data['src'], $data['deps'], $data['ver']);

        if ($this->_settings->isJsLoadInHead()) {
            $this->loadJsScripts(false, true, true);
        }
    }

    private function loadJsScripts($footer = true, $quiz = true, $toplist = false)
    {
        if ($quiz) {
            wp_enqueue_script(
                'wpTrivia_front_javascript',
                plugins_url('js/wpTrivia_front' . (WPPROQUIZ_DEV ? '' : '.min') . '.js', WPPROQUIZ_FILE),
                array('jquery-ui-sortable'),
                WPPROQUIZ_VERSION,
                $footer
            );

            wp_localize_script('wpTrivia_front_javascript', 'WpTriviaGlobal', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'loadData' => __('Loading', 'wp-trivia'),
                'questionNotSolved' => __('You must answer this question.', 'wp-trivia'),
                'fieldsNotFilled' => __('All fields have to be filled.', 'wp-trivia'),
                'connectionError' => __('Connection error. Please check your connection and try again.')
            ));
        }

        if ($toplist) {
            wp_enqueue_script(
                'wpTrivia_front_javascript_toplist',
                plugins_url('js/wpTrivia_toplist' . (WPPROQUIZ_DEV ? '' : '.min') . '.js', WPPROQUIZ_FILE),
                array('jquery-ui-sortable'),
                WPPROQUIZ_VERSION,
                $footer
            );

            if (!wp_script_is('wpTrivia_front_javascript')) {
                wp_localize_script('wpTrivia_front_javascript_toplist', 'WpTriviaGlobal', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'loadData' => __('Loading', 'wp-trivia'),
                    'questionNotSolved' => __('You must answer this question.', 'wp-trivia'),
                    'fieldsNotFilled' => __('All fields have to be filled.', 'wp-trivia')
                ));
            }
        }

        if (!$this->_settings->isTouchLibraryDeactivate()) {
            wp_enqueue_script(
                'jquery-ui-touch-punch',
                plugins_url('js/jquery.ui.touch-punch.min.js', WPPROQUIZ_FILE),
                array('jquery-ui-sortable'),
                '0.2.2',
                $footer
            );
        }
    }

    public function shortcode($attr)
    {
		$id = '';
		if (isset($attr) && is_array($attr) && count($attr) > 0){
			$id = $attr[0];
		}elseif (!empty($_GET['idQuiz'])){
			$id = $_GET['idQuiz'];
		}
        $content = '';

        if (!$this->_settings->isJsLoadInHead()) {
            $this->loadJsScripts();
        }

		ob_start();
        if (is_numeric($id)) {
            $this->handleShortCode($id);
        }else{
			echo '<div id="wpTrivia_quizList" class="clearfix">';
            $quizMapper = new WpTrivia_Model_QuizMapper();
            $quizList = $quizMapper->fetchAll();

            echo '<section class="gamification clearfix">';
            echo '<h1 class="headline2">' . __('Current Trivia.') . '</h1>';
            echo '<div class="wpTrivia_contentList row">';
            $validQuizList = array_filter($quizList, function($quiz){ 
                return $quiz->isValidToday(); 
            });
            foreach ($validQuizList as $quiz){
                $this->handleShortCode($quiz->getId(), true);
            }
			echo '</div>';
            echo '</section>';
            
            echo '<section class="gamification clearfix">';
            echo '<h1 class="headline2">' . __('Past Trivia.') . '</h1>';
            echo '<div class="wpTrivia_contentList row">';
            $pastQuizList = array_filter($quizList, function($quiz){ 
                $validToDate = strtotime($quiz->getValidToDate());
                $today = strtotime('today');
                return $validToDate && $today > $validToDate;
            });
            foreach ($pastQuizList as $quiz){
                $this->handleShortCode($quiz->getId(), true);
            }
			echo '</div>';
            echo '</section>';

			echo '</div>';
			echo '<div id="wpTrivia_quizDetails"></div>';
		}
		$content = ob_get_contents();
		ob_end_clean();

        if ($this->_settings->isAddRawShortcode()) {
            return '[raw]' . $content . '[/raw]';
        }

        return $content;
    }

    public function handleShortCode($id, $preview = false)
    {
        $view = new WpTrivia_View_FrontQuiz();

        $quizMapper = new WpTrivia_Model_QuizMapper();
        $questionMapper = new WpTrivia_Model_QuestionMapper();
        $formMapper = new WpTrivia_Model_FormMapper();

        $quiz = $quizMapper->fetch($id);

        $maxQuestion = false;

        if ($quiz->isShowMaxQuestion() && $quiz->getShowMaxQuestionValue() > 0) {

            $value = $quiz->getShowMaxQuestionValue();

            if ($quiz->isShowMaxQuestionPercent()) {
                $count = $questionMapper->count($id);

                $value = ceil($count * $value / 100);
            }

            $question = $questionMapper->fetchAll($id, true, $value);
            $maxQuestion = true;

        } else {
            $question = $questionMapper->fetchAll($id);
        }

        if (empty($quiz) || empty($question)) {
            echo '';

            return;
        }

        $view->quiz = $quiz;
        $view->question = $question;
        $view->forms = $formMapper->fetch($quiz->getId());

        if ($maxQuestion) {
            $view->showMaxQuestion();
        } else {
            $view->show($preview);
        }
    }

    public function shortcodeToplist($attr)
    {
        $id = $attr[0];
        $content = '';

        if (!$this->_settings->isJsLoadInHead()) {
            $this->loadJsScripts(true, false, true);
        }

        if (is_numeric($id)) {
            ob_start();

            $this->handleShortCodeToplist($id, isset($attr['q']));

            $content = ob_get_contents();

            ob_end_clean();
        }

        if ($this->_settings->isAddRawShortcode() && !isset($attr['q'])) {
            return '[raw]' . $content . '[/raw]';
        }

        return $content;
    }

    private function handleShortCodeToplist($quizId, $inQuiz = false)
    {
        $quizMapper = new WpTrivia_Model_QuizMapper();
        $view = new WpTrivia_View_FrontToplist();

        $quiz = $quizMapper->fetch($quizId);

        if ($quiz->getId() <= 0 || !$quiz->isToplistActivated()) {
            echo '';

            return;
        }

        $view->quiz = $quiz;
        $view->points = $quizMapper->sumQuestionPoints($quizId);
        $view->inQuiz = $inQuiz;
        $view->show();
    }

    private function loadSettings()
    {
        $mapper = new WpTrivia_Model_GlobalSettingsMapper();

        $this->_settings = $mapper->fetchAll();
	}
}
