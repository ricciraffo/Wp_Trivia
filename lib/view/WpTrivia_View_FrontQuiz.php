<?php

/**
 * @property WpTrivia_Model_Quiz quiz
 * @property WpTrivia_Model_Question[] question
 * @property WpTrivia_Model_Category[] category
 * @property WpTrivia_Model_Form[] forms
 */
class WpTrivia_View_FrontQuiz extends WpTrivia_View_View
{
    private $_clozeTemp = array();
    private $_assessmetTemp = array();

    private $_buttonNames = array();

    private function loadButtonNames()
    {
        if (!empty($this->_buttonNames)) {
            return;
        }

        $names = array(
            'start_quiz' => __('Start quiz', 'wp-trivia'),
            'quiz_summary' => __('Quiz-summary', 'wp-trivia'),
            'finish_quiz' => __('Finish quiz', 'wp-trivia'),
            'quiz_is_loading' => __('Quiz is loading...', 'wp-trivia'),
            'lock_box_msg' => __('You have already completed the quiz before. Hence you can not start it again.',
                'wp-trivia'),
            'only_registered_user_msg' => __('You must sign in or sign up to start the quiz.', 'wp-trivia'),
            'prerequisite_msg' => __('You have to finish following quiz, to start this quiz:', 'wp-trivia')
        );

        $this->_buttonNames = ((array)apply_filters('wpTrivia_filter_frontButtonNames', $names, $this)) + $names;
    }

    /**
     * @param $data WpTrivia_Model_AnswerTypes
     *
     * @return array
     */
    private function getFreeCorrect($data)
    {
        $t = str_replace("\r\n", "\n", strtolower($data->getAnswer()));
        $t = str_replace("\r", "\n", $t);
        $t = explode("\n", $t);

        return array_values(array_filter(array_map('trim', $t), array($this, 'removeEmptyElements')));
    }

    private function removeEmptyElements($v)
    {
        return !empty($v) || $v === '0';
    }

    public function show($preview = false)
    {
        $this->loadButtonNames();

        $question_count = count($this->question);

        $result = $this->quiz->getResultText();

        if (!$this->quiz->isResultGradeEnabled()) {
            $result = array(
                'text' => array($result),
                'prozent' => array(0)
            );
        }

        $resultsProzent = json_encode($result['prozent']);

        $resultReplace = array();

        foreach ($this->forms as $form) {
            /* @var $form WpTrivia_Model_Form */

            $resultReplace['$form{' . $form->getSort() . '}'] = '<span class="wpTrivia_resultForm" data-form_id="' . $form->getFormId() . '"></span>';
        }

        foreach ($result['text'] as &$text) {
            $text = str_replace(array_keys($resultReplace), $resultReplace, $text);
        }

        ?>
        <div class="wpTrivia_content" id="wpTrivia_<?php echo $this->quiz->getId(); ?>">
            <?php

            echo '<h2>', $this->quiz->getName(), '</h2>';

            $this->showTimeLimitBox();
            $this->showCheckPageBox($question_count);
            $this->showInfoPageBox();
            $this->showStartQuizBox();
            $this->showLockBox();
            $this->showLoadQuizBox();
            $this->showStartOnlyRegisteredUserBox();
            $this->showPrerequisiteBox();
            $this->showResultBox($result, $question_count);

            if ($this->quiz->getToplistDataShowIn() == WpTrivia_Model_Quiz::QUIZ_TOPLIST_SHOW_IN_BUTTON) {
                $this->showToplistInButtonBox();
            }

            $this->showReviewBox($question_count);
            $this->showQuizAnker();

            $quizData = $this->showQuizBox($question_count);

            ?>
        </div>
        <?php

        $bo = $this->createOption($preview);
        ?>
        <script type="text/javascript">
            window.wpTriviaInitList = window.wpTriviaInitList || [];

            window.wpTriviaInitList.push({
                id: '#wpTrivia_<?php echo $this->quiz->getId(); ?>',
                init: {
                    quizId: <?php echo (int)$this->quiz->getId(); ?>,
                    globalPoints: <?php echo (int)$quizData['globalPoints']; ?>,
                    timelimit: <?php echo (int)$this->quiz->getTimeLimit(); ?>,
                    resultsGrade: <?php echo $resultsProzent; ?>,
                    bo: <?php echo $bo ?>,
                    qpp: <?php echo $this->quiz->getQuestionsPerPage(); ?>,
                    catPoints: <?php echo json_encode($quizData['catPoints']); ?>,
                    formPos: <?php echo (int)$this->quiz->getFormShowPosition(); ?>,
                    lbn: <?php echo json_encode(($this->quiz->isShowReviewQuestion() && !$this->quiz->isQuizSummaryHide()) ? $this->_buttonNames['quiz_summary'] : $this->_buttonNames['finish_quiz']); ?>,
                    json: <?php echo json_encode($quizData['json']); ?>
                }
            });
        </script>
        <?php
    }

    private function createOption($preview)
    {
        $bo = 0;

        $bo |= ((int)$this->quiz->isDisabledAnswerMark()) << 2;
        $bo |= ((int)($this->quiz->isQuizRunOnce() || $this->quiz->isPrerequisite() || $this->quiz->isStartOnlyRegisteredUser())) << 3;
        $bo |= ((int)$preview) << 4;
        $bo |= ((int)get_option('wpTrivia_corsActivated')) << 5;
        $bo |= ((int)$this->quiz->isToplistDataAddAutomatic()) << 6;
        $bo |= ((int)$this->quiz->isShowReviewQuestion()) << 7;
        $bo |= ((int)$this->quiz->isQuizSummaryHide()) << 8;
        $bo |= ((int)(!$this->quiz->isSkipQuestionDisabled() && $this->quiz->isShowReviewQuestion())) << 9;
        $bo |= ((int)$this->quiz->isAutostart()) << 10;
        $bo |= ((int)$this->quiz->isForcingQuestionSolve()) << 11;
        $bo |= ((int)$this->quiz->isHideQuestionPositionOverview()) << 12;
        $bo |= ((int)$this->quiz->isFormActivated()) << 13;
        $bo |= ((int)$this->quiz->isShowMaxQuestion()) << 14;
        $bo |= ((int)$this->quiz->isSortCategories()) << 15;

        return $bo;
    }

    public function showMaxQuestion()
    {
        $this->loadButtonNames();

        $question_count = count($this->question);

        $result = $this->quiz->getResultText();

        if (!$this->quiz->isResultGradeEnabled()) {
            $result = array(
                'text' => array($result),
                'prozent' => array(0)
            );
        }

        $resultsProzent = json_encode($result['prozent']);

        ?>
        <div class="wpTrivia_content" id="wpTrivia_<?php echo $this->quiz->getId(); ?>">
            <?php

            echo '<h2>', $this->quiz->getName(), '</h2>';

            $this->showTimeLimitBox();
            $this->showCheckPageBox($question_count);
            $this->showInfoPageBox();
            $this->showStartQuizBox();
            $this->showLockBox();
            $this->showLoadQuizBox();
            $this->showStartOnlyRegisteredUserBox();
            $this->showPrerequisiteBox();
            $this->showResultBox($result, $question_count);

            if ($this->quiz->getToplistDataShowIn() == WpTrivia_Model_Quiz::QUIZ_TOPLIST_SHOW_IN_BUTTON) {
                $this->showToplistInButtonBox();
            }

            $this->showReviewBox($question_count);
            $this->showQuizAnker();
            ?>
        </div>
        <?php

        $bo = $this->createOption(false);

        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                $('#wpTrivia_<?php echo $this->quiz->getId(); ?>').wpTriviaFront({
                    quizId: <?php echo (int)$this->quiz->getId(); ?>,
                    timelimit: <?php echo (int)$this->quiz->getTimeLimit(); ?>,
                    resultsGrade: <?php echo $resultsProzent; ?>,
                    bo: <?php echo $bo ?>,
                    qpp: <?php echo $this->quiz->getQuestionsPerPage(); ?>,
                    formPos: <?php echo (int)$this->quiz->getFormShowPosition(); ?>,
                    lbn: <?php echo json_encode(($this->quiz->isShowReviewQuestion() && !$this->quiz->isQuizSummaryHide()) ? $this->_buttonNames['quiz_summary'] : $this->_buttonNames['finish_quiz']); ?>
                });
            });
        </script>
        <?php
    }

    public function getQuizData()
    {
        ob_start();

        $this->loadButtonNames();

        $quizData = $this->showQuizBox(count($this->question));

        $quizData['content'] = ob_get_contents();

        ob_end_clean();

        return $quizData;
    }

    private function showQuizAnker()
    {
        ?>
        <div class="wpTrivia_quizAnker" style="display: none;"></div>
        <?php
    }

    private function showAddToplist()
    {
        ?>
        <div class="wpTrivia_addToplist" style="display: none;">
            <span style="font-weight: bold;"><?php _e('Your result has been entered into leaderboard',
                    'wp-trivia'); ?></span>

            <div style="margin-top: 6px;">
                <div class="wpTrivia_addToplistMessage" style="display: none;"><?php _e('Loading',
                        'wp-trivia'); ?></div>
                <div class="wpTrivia_addBox">
                    <div>
						<span>
							<label>
                                <?php _e('Name', 'wp-trivia'); ?>: <input type="text" placeholder="<?php _e('Name',
                                    'wp-trivia'); ?>" name="wpTrivia_toplistName" maxlength="15" size="16"
                                                                            style="width: 150px;">
                            </label>
							<label>
                                <?php _e('E-Mail', 'wp-trivia'); ?>: <input type="email"
                                                                              placeholder="<?php _e('E-Mail',
                                                                                  'wp-trivia'); ?>"
                                                                              name="wpTrivia_toplistEmail" size="20"
                                                                              style="width: 150px;">
                            </label>
						</span>

                        <div style="margin-top: 5px;">
                            <label>
                                <?php _e('Captcha', 'wp-trivia'); ?>: <input type="text" name="wpTrivia_captcha"
                                                                               size="8" style="width: 50px;">
                            </label>
                            <input type="hidden" name="wpTrivia_captchaPrefix" value="0">
                            <img alt="captcha" src="" class="wpTrivia_captchaImg" style="vertical-align: middle;">
                        </div>
                    </div>
                    <input class="wpTrivia_button2" type="submit" value="<?php _e('Send', 'wp-trivia'); ?>"
                           name="wpTrivia_toplistAdd">
                </div>
            </div>
        </div>
        <?php
    }

    private function fetchCloze($answer_text)
    {
        preg_match_all('#\{(.*?)(?:\|(\d+))?(?:[\s]+)?\}#im', $answer_text, $matches, PREG_SET_ORDER);

        $data = array();

        foreach ($matches as $k => $v) {
            $text = $v[1];
            $points = !empty($v[2]) ? (int)$v[2] : 1;
            $rowText = $multiTextData = array();
            $len = array();

            if (preg_match_all('#\[(.*?)\]#im', $text, $multiTextMatches)) {
                foreach ($multiTextMatches[1] as $multiText) {
                    $x = mb_strtolower(trim(html_entity_decode($multiText, ENT_QUOTES)));

                    $len[] = strlen($x);
                    $multiTextData[] = $x;
                    $rowText[] = $multiText;
                }
            } else {
                $x = mb_strtolower(trim(html_entity_decode($text, ENT_QUOTES)));

                $len[] = strlen($x);
                $multiTextData[] = $x;
                $rowText[] = $text;
            }

            $a = '<span class="wpTrivia_cloze"><input data-wordlen="' . max($len) . '" type="text" value=""> ';
            $a .= '<span class="wpTrivia_clozeCorrect" style="display: none;">(' . implode(', ',
                    $rowText) . ')</span></span>';

            $data['correct'][] = $multiTextData;
            $data['points'][] = $points;
            $data['data'][] = $a;
        }

        $data['replace'] = preg_replace('#\{(.*?)(?:\|(\d+))?(?:[\s]+)?\}#im', '@@wpTriviaCloze@@', $answer_text);

        return $data;
    }

    private function clozeCallback($t)
    {
        $a = array_shift($this->_clozeTemp);

        return $a === null ? '' : $a;
    }

    private function fetchAssessment($answerText, $quizId, $questionId)
    {
        preg_match_all('#\{(.*?)\}#im', $answerText, $matches);

        $this->_assessmetTemp = array();
        $data = array();

        for ($i = 0, $ci = count($matches[1]); $i < $ci; $i++) {
            $match = $matches[1][$i];

            preg_match_all('#\[([^\|\]]+)(?:\|(\d+))?\]#im', $match, $ms);

            $a = '';

            for ($j = 0, $cj = count($ms[1]); $j < $cj; $j++) {
                $v = $ms[1][$j];

                $a .= '<label>
					<input type="radio" value="' . ($j + 1) . '" name="question_' . $quizId . '_' . $questionId . '_' . $i . '" class="wpTrivia_questionInput" data-index="' . $i . '">
					' . $v . '
				</label>';

            }

            $this->_assessmetTemp[] = $a;
        }

        $data['replace'] = preg_replace('#\{(.*?)\}#im', '@@wpTriviaAssessment@@', $answerText);

        return $data;
    }

    private function assessmentCallback($t)
    {
        $a = array_shift($this->_assessmetTemp);

        return $a === null ? '' : $a;
    }

    private function showFormBox()
    {
        $info = '<div class="wpTrivia_invalidate">' . __('You must fill out this field.', 'wp-trivia') . '</div>';

        $validateText = array(
            WpTrivia_Model_Form::FORM_TYPE_NUMBER => __('You must specify a number.', 'wp-trivia'),
            WpTrivia_Model_Form::FORM_TYPE_TEXT => __('You must specify a text.', 'wp-trivia'),
            WpTrivia_Model_Form::FORM_TYPE_EMAIL => __('You must specify an email address.', 'wp-trivia'),
            WpTrivia_Model_Form::FORM_TYPE_DATE => __('You must specify a date.', 'wp-trivia')
        );
        ?>
        <div class="wpTrivia_forms">
            <table>
                <tbody>

                <?php
                $index = 0;
                foreach ($this->forms as $form) {
                    /* @var $form WpTrivia_Model_Form */

                    $id = 'forms_' . $this->quiz->getId() . '_' . $index++;
                    $name = 'wpTrivia_field_' . $form->getFormId();
                    ?>
                    <tr>
                        <td>
                            <?php
                            echo '<label for="' . $id . '">';
                            echo esc_html($form->getFieldname());
                            echo $form->isRequired() ? '<span class="wpTrivia_required">*</span>' : '';
                            echo '</label>';
                            ?>
                        </td>
                        <td>

                            <?php
                            switch ($form->getType()) {
                                case WpTrivia_Model_Form::FORM_TYPE_TEXT:
                                case WpTrivia_Model_Form::FORM_TYPE_EMAIL:
                                case WpTrivia_Model_Form::FORM_TYPE_NUMBER:
                                    echo '<input name="' . $name . '" id="' . $id . '" type="text" ',
                                        'data-required="' . (int)$form->isRequired() . '" data-type="' . $form->getType() . '" data-form_id="' . $form->getFormId() . '">';
                                    break;
                                case WpTrivia_Model_Form::FORM_TYPE_TEXTAREA:
                                    echo '<textarea rows="5" cols="20" name="' . $name . '" id="' . $id . '" ',
                                        'data-required="' . (int)$form->isRequired() . '" data-type="' . $form->getType() . '" data-form_id="' . $form->getFormId() . '"></textarea>';
                                    break;
                                case WpTrivia_Model_Form::FORM_TYPE_CHECKBOX:
                                    echo '<input name="' . $name . '" id="' . $id . '" type="checkbox" value="1"',
                                        'data-required="' . (int)$form->isRequired() . '" data-type="' . $form->getType() . '" data-form_id="' . $form->getFormId() . '">';
                                    break;
                                case WpTrivia_Model_Form::FORM_TYPE_DATE:
                                    echo '<div data-required="' . (int)$form->isRequired() . '" data-type="' . $form->getType() . '" class="wpTrivia_formFields" data-form_id="' . $form->getFormId() . '">';
                                    echo WpTrivia_Helper_Until::getDatePicker(get_option('date_format', 'j. F Y'),
                                        $name);
                                    echo '</div>';
                                    break;
                                case WpTrivia_Model_Form::FORM_TYPE_RADIO:
                                    echo '<div data-required="' . (int)$form->isRequired() . '" data-type="' . $form->getType() . '" class="wpTrivia_formFields" data-form_id="' . $form->getFormId() . '">';

                                    if ($form->getData() !== null) {
                                        foreach ($form->getData() as $data) {
                                            echo '<label>';
                                            echo '<input name="' . $name . '" type="radio" value="' . esc_attr($data) . '"> ',
                                            esc_html($data);
                                            echo '</label> ';
                                        }
                                    }

                                    echo '</div>';

                                    break;
                                case WpTrivia_Model_Form::FORM_TYPE_SELECT:
                                    if ($form->getData() !== null) {
                                        echo '<select name="' . $name . '" id="' . $id . '" ',
                                            'data-required="' . (int)$form->isRequired() . '" data-type="' . $form->getType() . '" data-form_id="' . $form->getFormId() . '">';
                                        echo '<option value=""></option>';

                                        foreach ($form->getData() as $data) {
                                            echo '<option value="' . esc_attr($data) . '">', esc_html($data), '</option>';
                                        }

                                        echo '</select>';
                                    }
                                    break;
                                case WpTrivia_Model_Form::FORM_TYPE_YES_NO:
                                    echo '<div data-required="' . (int)$form->isRequired() . '" data-type="' . $form->getType() . '" class="wpTrivia_formFields" data-form_id="' . $form->getFormId() . '">';
                                    echo '<label>';
                                    echo '<input name="' . $name . '" type="radio" value="1"> ',
                                    __('Yes', 'wp-trivia');
                                    echo '</label> ';

                                    echo '<label>';
                                    echo '<input name="' . $name . '" type="radio" value="0"> ',
                                    __('No', 'wp-trivia');
                                    echo '</label> ';
                                    echo '</div>';
                                    break;
                            }

                            if (isset($validateText[$form->getType()])) {
                                echo '<div class="wpTrivia_invalidate">' . $validateText[$form->getType()] . '</div>';
                            } else {
                                echo '<div class="wpTrivia_invalidate">' . __('You must fill out this field.',
                                        'wp-trivia') . '</div>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>

        </div>

        <?php
    }

    private function showLockBox()
    {
        ?>
        <div style="display: none;" class="wpTrivia_lock">
            <p>
                <?php echo $this->_buttonNames['lock_box_msg']; ?>
            </p>
        </div>
        <?php
    }

    private function showStartOnlyRegisteredUserBox()
    {
        ?>
        <div style="display: none;" class="wpTrivia_startOnlyRegisteredUser">
            <p>
                <?php echo $this->_buttonNames['only_registered_user_msg']; ?>
            </p>
        </div>
        <?php
    }

    private function showPrerequisiteBox()
    {
        ?>
        <div style="display: none;" class="wpTrivia_prerequisite">
            <p>
                <?php echo $this->_buttonNames['prerequisite_msg']; ?>
                <span></span>
            </p>
        </div>
        <?php
    }

    private function showCheckPageBox($questionCount)
    {
        ?>
        <div class="wpTrivia_checkPage" style="display: none;">
            <h4 class="wpTrivia_header"><?php echo $this->_buttonNames['quiz_summary']; ?></h4>

            <p>
                <?php printf(__('%s of %s questions completed', 'wp-trivia'), '<span>0</span>', $questionCount); ?>
            </p>

            <p><?php _e('Questions', 'wp-trivia'); ?>:</p>

            <div style="margin-bottom: 20px;" class="wpTrivia_box">
                <ol>
                    <?php for ($xy = 1; $xy <= $questionCount; $xy++) { ?>
                        <li><?php echo $xy; ?></li>
                    <?php } ?>
                </ol>
                <div style="clear: both;"></div>
            </div>

            <?php
            if ($this->quiz->isFormActivated() && $this->quiz->getFormShowPosition() == WpTrivia_Model_Quiz::QUIZ_FORM_POSITION_END
                && ($this->quiz->isShowReviewQuestion() && !$this->quiz->isQuizSummaryHide())
            ) {

                ?>
                <h4 class="wpTrivia_header"><?php _e('Information', 'wp-trivia'); ?></h4>
                <?php
                $this->showFormBox();
            }

            ?>

            <input type="button" name="endQuizSummary" value="<?php echo $this->_buttonNames['finish_quiz']; ?>"
                   class="wpTrivia_button">
        </div>
        <?php
    }

    private function showInfoPageBox()
    {
        ?>
        <div class="wpTrivia_infopage" style="display: none;">
            <h4><?php _e('Information', 'wp-trivia'); ?></h4>

            <?php
            if ($this->quiz->isFormActivated() && $this->quiz->getFormShowPosition() == WpTrivia_Model_Quiz::QUIZ_FORM_POSITION_END
                && (!$this->quiz->isShowReviewQuestion() || $this->quiz->isQuizSummaryHide())
            ) {
                $this->showFormBox();
            }

            ?>

            <input type="button" name="endInfopage" value="<?php echo $this->_buttonNames['finish_quiz']; ?>"
                   class="wpTrivia_button">
        </div>
        <?php
    }

    private function showStartQuizBox()
    {
        ?>
        <div class="wpTrivia_text">
            <p>
                <?php echo do_shortcode(apply_filters('comment_text', $this->quiz->getText())); ?>
            </p>

            <?php
            if ($this->quiz->isFormActivated() && $this->quiz->getFormShowPosition() == WpTrivia_Model_Quiz::QUIZ_FORM_POSITION_START) {
                $this->showFormBox();
            }
            ?>

            <div>
                <input class="wpTrivia_button" type="button" value="<?php echo $this->_buttonNames['start_quiz']; ?>"
                       name="startQuiz">
            </div>
        </div>
        <?php
    }

    private function showTimeLimitBox()
    {
        ?>
        <div style="display: none;" class="wpTrivia_time_limit">
            <div class="time"><?php _e('Time limit', 'wp-trivia'); ?>: <span>0</span></div>
            <div class="wpTrivia_progress"></div>
        </div>
        <?php
    }

    private function showReviewBox($questionCount)
    {
        ?>
        <div class="wpTrivia_reviewDiv" style="display: none;">
            <div class="wpTrivia_reviewQuestion">
                <ol>
                    <?php for ($xy = 1; $xy <= $questionCount; $xy++) { ?>
                        <li><?php echo $xy; ?></li>
                    <?php } ?>
                </ol>
                <div style="display: none;"></div>
            </div>
            <div class="wpTrivia_reviewLegend">
                <ol>
                    <li>
                        <span class="wpTrivia_reviewColor" style="background-color: #6CA54C;"></span>
                        <span class="wpTrivia_reviewText"><?php _e('Answered', 'wp-trivia'); ?></span>
                    </li>
                    <li>
                        <span class="wpTrivia_reviewColor" style="background-color: #FFB800;"></span>
                        <span class="wpTrivia_reviewText"><?php _e('Review', 'wp-trivia'); ?></span>
                    </li>
                </ol>
                <div style="clear: both;"></div>
            </div>
        </div>
        <?php
    }

    private function showResultBox($result, $questionCount)
    {
        ?>
        <div style="display: none;" class="wpTrivia_results">
            <h4 class="wpTrivia_header"><?php _e('Results', 'wp-trivia'); ?></h4>
            <?php if (!$this->quiz->isHideResultCorrectQuestion()) { ?>
                <p>
                    <?php printf(__('%s of %s questions answered correctly', 'wp-trivia'),
                        '<span class="wpTrivia_correct_answer">0</span>', '<span>' . $questionCount . '</span>'); ?>
                </p>
            <?php }
            if (!$this->quiz->isHideResultQuizTime()) { ?>
                <p class="wpTrivia_quiz_time">
                    <?php _e('Your time: <span></span>', 'wp-trivia'); ?>
                </p>
            <?php } ?>
            <p class="wpTrivia_time_limit_expired" style="display: none;">
                <?php _e('Time has elapsed', 'wp-trivia'); ?>
            </p>
            <?php if (!$this->quiz->isHideResultPoints()) { ?>
                <p class="wpTrivia_points">
                    <?php printf(__('You have reached %s of %s points, (%s)', 'wp-trivia'), '<span>0</span>',
                        '<span>0</span>', '<span>0</span>'); ?>
                </p>
            <?php } ?>
            <?php if ($this->quiz->isShowAverageResult()) { ?>
                <div class="wpTrivia_resultTable">
                    <table>
                        <tbody>
                        <tr>
                            <td class="wpTrivia_resultName"><?php _e('Average score', 'wp-trivia'); ?></td>
                            <td class="wpTrivia_resultValue">
                                <div style="background-color: #6CA54C;">&nbsp;</div>
                                <span>&nbsp;</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="wpTrivia_resultName"><?php _e('Your score', 'wp-trivia'); ?></td>
                            <td class="wpTrivia_resultValue">
                                <div style="background-color: #F79646;">&nbsp;</div>
                                <span>&nbsp;</span>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
            <div class="wpTrivia_catOverview" <?php $this->isDisplayNone($this->quiz->isShowCategoryScore()); ?>>
                <h4><?php _e('Categories', 'wp-trivia'); ?></h4>

                <div style="margin-top: 10px;">
                    <ol>
                        <?php foreach ($this->category as $cat) {
                            if (!$cat->getCategoryId()) {
                                $cat->setCategoryName(__('Not categorized', 'wp-trivia'));
                            }
                            ?>
                            <li data-category_id="<?php echo $cat->getCategoryId(); ?>">
                                <span class="wpTrivia_catName"><?php echo $cat->getCategoryName(); ?></span>
                                <span class="wpTrivia_catPercent">0%</span>
                            </li>
                        <?php } ?>
                    </ol>
                </div>
            </div>
            <div>
                <ul class="wpTrivia_resultsList">
                    <?php foreach ($result['text'] as $resultText) { ?>
                        <li style="display: none;">
                            <div>
                                <?php echo do_shortcode(apply_filters('comment_text', $resultText)); ?>
                            </div>
                        </li>
                    <?php } ?>
                </ul>
            </div>
            <?php
            if ($this->quiz->isToplistActivated()) {
                if ($this->quiz->getToplistDataShowIn() == WpTrivia_Model_Quiz::QUIZ_TOPLIST_SHOW_IN_NORMAL) {
                    echo do_shortcode('[WpTrivia_toplist ' . $this->quiz->getId() . ' q="true"]');
                }

                $this->showAddToplist();
            }
            ?>
            <div style="margin: 10px 0px;">
                <?php if ($this->quiz->isToplistActivated() && $this->quiz->getToplistDataShowIn() == WpTrivia_Model_Quiz::QUIZ_TOPLIST_SHOW_IN_BUTTON) { ?>
                    <input class="wpTrivia_button" type="button" name="showToplist"
                           value="<?php _e('Show leaderboard', 'wp-trivia'); ?>">
                <?php } ?>
            </div>
        </div>
        <?php
    }

    private function showToplistInButtonBox()
    {
        ?>
        <div class="wpTrivia_toplistShowInButton" style="display: none;">
            <?php echo do_shortcode('[WpTrivia_toplist ' . $this->quiz->getId() . ' q="true"]'); ?>
        </div>
        <?php
    }

    private function showQuizBox($questionCount)
    {
        $globalPoints = 0;
        $json = array();
        $catPoints = array();
        ?>
        <div style="display: none;" class="wpTrivia_quiz">
            <ol class="wpTrivia_list">
                <?php
                $index = 0;
                foreach ($this->question as $question) {
                    $index++;

                    /* @var $answerArray WpTrivia_Model_AnswerTypes[] */
                    $answerArray = $question->getAnswerData();

                    $globalPoints += $question->getPoints();

                    $json[$question->getId()]['type'] = $question->getAnswerType();
                    $json[$question->getId()]['id'] = (int)$question->getId();
                    $json[$question->getId()]['catId'] = (int)$question->getCategoryId();

                    if ($question->isAnswerPointsActivated() && $question->isAnswerPointsDiffModusActivated() && $question->isDisableCorrect()) {
                        $json[$question->getId()]['disCorrect'] = (int)$question->isDisableCorrect();
                    }

                    if (!isset($catPoints[$question->getCategoryId()])) {
                        $catPoints[$question->getCategoryId()] = 0;
                    }

                    $catPoints[$question->getCategoryId()] += $question->getPoints();

                    if (!$question->isAnswerPointsActivated()) {
                        $json[$question->getId()]['points'] = $question->getPoints();
                    }

                    if ($question->isAnswerPointsActivated() && $question->isAnswerPointsDiffModusActivated()) {
                        $json[$question->getId()]['diffMode'] = 1;
                    }

                    ?>
                    <li class="wpTrivia_listItem" style="display: none;">
                        <div
                            class="wpTrivia_question_page" <?php $this->isDisplayNone(!$this->quiz->isHideQuestionPositionOverview()); ?> >
                            <?php printf(__('Question %s of %s', 'wp-trivia'), '<span>' . $index . '</span>',
                                '<span>' . $questionCount . '</span>'); ?>
                        </div>
                        <h5 style="<?php echo $this->quiz->isHideQuestionNumbering() ? 'display: none;' : 'display: inline-block;' ?>"
                            class="wpTrivia_header">
                            <span><?php echo $index; ?></span>. <?php _e('Question', 'wp-trivia'); ?>
                        </h5>

                        <?php if ($this->quiz->isShowPoints()) { ?>
                            <span style="font-weight: bold; float: right;"><?php printf(__('%d points', 'wp-trivia'),
                                    $question->getPoints()); ?></span>
                            <div style="clear: both;"></div>
                        <?php } ?>

                        <?php if ($question->getCategoryId() && $this->quiz->isShowCategory()) { ?>
                            <div style="font-weight: bold; padding-top: 5px;">
                                <?php printf(__('Category: %s', 'wp-trivia'),
                                    esc_html($question->getCategoryName())); ?>
                            </div>
                        <?php } ?>
                        <div class="wpTrivia_question" style="margin: 10px 0 0 0;">
                            <div class="wpTrivia_question_text">
                                <?php echo do_shortcode(apply_filters('comment_text', $question->getQuestion())); ?>
                            </div>
                            <?php if ($question->getAnswerType() === 'matrix_sort_answer') { ?>
                                <div class="wpTrivia_matrixSortString">
                                    <h5 class="wpTrivia_header"><?php _e('Sort elements', 'wp-trivia'); ?></h5>
                                    <ul class="wpTrivia_sortStringList">
                                        <?php
                                        $matrix = array();
                                        foreach ($answerArray as $k => $v) {
                                            $matrix[$k][] = $k;

                                            foreach ($answerArray as $k2 => $v2) {
                                                if ($k != $k2) {
                                                    if ($v->getAnswer() == $v2->getAnswer()) {
                                                        $matrix[$k][] = $k2;
                                                    } else {
                                                        if ($v->getSortString() == $v2->getSortString()) {
                                                            $matrix[$k][] = $k2;
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        foreach ($answerArray as $k => $v) {
                                            ?>
                                            <li class="wpTrivia_sortStringItem" data-pos="<?php echo $k; ?>"
                                                data-correct="<?php echo implode(',', $matrix[$k]); ?>">
                                                <?php echo $v->isSortStringHtml() ? $v->getSortString() : esc_html($v->getSortString()); ?>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                    <div style="clear: both;"></div>
                                </div>
                            <?php } ?>
                            <ul class="wpTrivia_questionList" data-question_id="<?php echo $question->getId(); ?>"
                                data-type="<?php echo $question->getAnswerType(); ?>">
                                <?php
                                $answer_index = 0;

                                foreach ($answerArray as $v) {
                                    $answer_text = $v->isHtml() ? $v->getAnswer() : esc_html($v->getAnswer());

                                    if ($answer_text == '') {
                                        continue;
                                    }

                                    if ($question->isAnswerPointsActivated()) {
                                        $json[$question->getId()]['points'][] = $v->getPoints();
                                    }

                                    ?>

                                    <li class="wpTrivia_questionListItem" data-pos="<?php echo $answer_index; ?>">

                                        <?php if ($question->getAnswerType() === 'single' || $question->getAnswerType() === 'multiple') { ?>
                                            <?php $json[$question->getId()]['correct'][] = (int)$v->isCorrect(); ?>
                                            <span <?php echo $this->quiz->isNumberedAnswer() ? '' : 'style="display:none;"' ?>></span>
                                            <label>
                                                <input class="wpTrivia_questionInput"
                                                       type="<?php echo $question->getAnswerType() === 'single' ? 'radio' : 'checkbox'; ?>"
                                                       name="question_<?php echo $this->quiz->getId(); ?>_<?php echo $question->getId(); ?>"
                                                       value="<?php echo($answer_index + 1); ?>"> <?php echo $answer_text; ?>
                                            </label>

                                        <?php } else {
                                            if ($question->getAnswerType() === 'sort_answer') { ?>
                                                <?php $json[$question->getId()]['correct'][] = (int)$answer_index; ?>
                                                <div class="wpTrivia_sortable">
                                                    <?php echo $answer_text; ?>
                                                </div>
                                            <?php } else {
                                                if ($question->getAnswerType() === 'free_answer') { ?>
                                                    <?php $json[$question->getId()]['correct'] = $this->getFreeCorrect($v); ?>
                                                    <label>
                                                        <input class="wpTrivia_questionInput" type="text"
                                                               name="question_<?php echo $this->quiz->getId(); ?>_<?php echo $question->getId(); ?>"
                                                               style="width: 300px;">
                                                    </label>
                                                <?php } else {
                                                    if ($question->getAnswerType() === 'matrix_sort_answer') { ?>
                                                        <?php
                                                        $json[$question->getId()]['correct'][] = (int)$answer_index;
                                                        $msacwValue = $question->getMatrixSortAnswerCriteriaWidth() > 0 ? $question->getMatrixSortAnswerCriteriaWidth() : 20;
                                                        ?>
                                                        <table>
                                                            <tbody>
                                                            <tr class="wpTrivia_mextrixTr">
                                                                <td width="<?php echo $msacwValue; ?>%">
                                                                    <div
                                                                        class="wpTrivia_maxtrixSortText"><?php echo $answer_text; ?></div>
                                                                </td>
                                                                <td width="<?php echo 100 - $msacwValue; ?>%">
                                                                    <ul class="wpTrivia_maxtrixSortCriterion"></ul>
                                                                </td>
                                                            </tr>
                                                            </tbody>
                                                        </table>

                                                    <?php } else {
                                                        if ($question->getAnswerType() === 'cloze_answer') {
                                                            $clozeData = $this->fetchCloze($v->getAnswer());

                                                            $this->_clozeTemp = $clozeData['data'];

                                                            $json[$question->getId()]['correct'] = $clozeData['correct'];

                                                            if ($question->isAnswerPointsActivated()) {
                                                                $json[$question->getId()]['points'] = $clozeData['points'];
                                                            }

                                                            $cloze = do_shortcode(apply_filters('comment_text',
                                                                $clozeData['replace']));
                                                            $cloze = $clozeData['replace'];

                                                            echo preg_replace_callback('#@@wpTriviaCloze@@#im',
                                                                array($this, 'clozeCallback'), $cloze);
                                                        } else {
                                                            if ($question->getAnswerType() === 'assessment_answer') {
                                                                $assessmentData = $this->fetchAssessment($v->getAnswer(),
                                                                    $this->quiz->getId(), $question->getId());

                                                                $assessment = do_shortcode(apply_filters('comment_text',
                                                                    $assessmentData['replace']));

                                                                echo preg_replace_callback('#@@wpTriviaAssessment@@#im',
                                                                    array($this, 'assessmentCallback'), $assessment);

                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        } ?>
                                    </li>
                                    <?php
                                    $answer_index++;
                                }
                                ?>
                            </ul>
                        </div>
                        <?php if (!$this->quiz->isHideAnswerMessageBox()) { ?>
                            <div class="wpTrivia_response" style="display: none;">
                                <div style="display: none;" class="wpTrivia_correct">
                                    <?php if ($question->isShowPointsInBox() && $question->isAnswerPointsActivated()) { ?>
                                        <div>
									<span style="float: left;" class="wpTrivia_respone_span">
										<?php _e('Correct', 'wp-trivia'); ?>
									</span>
                                            <span
                                                style="float: right;"><?php echo $question->getPoints() . ' / ' . $question->getPoints(); ?> <?php _e('Points',
                                                    'wp-trivia'); ?></span>

                                            <div style="clear: both;"></div>
                                        </div>
                                    <?php } else { ?>
                                        <span class="wpTrivia_respone_span">
									<?php _e('Correct', 'wp-trivia'); ?>
								</span><br>
                                    <?php }
                                    $_correctMsg = trim(do_shortcode(apply_filters('comment_text',
                                        $question->getCorrectMsg())));

                                    if (strpos($_correctMsg, '<p') === 0) {
                                        echo $_correctMsg;
                                    } else {
                                        echo '<p>', $_correctMsg, '</p>';
                                    }
                                    ?>
                                </div>
                                <div style="display: none;" class="wpTrivia_incorrect">
                                    <?php if ($question->isShowPointsInBox() && $question->isAnswerPointsActivated()) { ?>
                                        <div>
									<span style="float: left;" class="wpTrivia_respone_span">
										<?php _e('Incorrect', 'wp-trivia'); ?>
									</span>
                                            <span style="float: right;"><span
                                                    class="wpTrivia_responsePoints"></span> / <?php echo $question->getPoints(); ?> <?php _e('Points',
                                                    'wp-trivia'); ?></span>

                                            <div style="clear: both;"></div>
                                        </div>
                                    <?php } else { ?>
                                        <span class="wpTrivia_respone_span">
									<?php _e('Incorrect', 'wp-trivia'); ?>
								</span><br>
                                    <?php }

                                    if ($question->isCorrectSameText()) {
                                        $_incorrectMsg = do_shortcode(apply_filters('comment_text',
                                            $question->getCorrectMsg()));
                                    } else {
                                        $_incorrectMsg = do_shortcode(apply_filters('comment_text',
                                            $question->getIncorrectMsg()));
                                    }

                                    if (strpos($_incorrectMsg, '<p') === 0) {
                                        echo $_incorrectMsg;
                                    } else {
                                        echo '<p>', $_incorrectMsg, '</p>';
                                    }

                                    ?>
                                </div>
                            </div>
                        <?php } ?>

                        <?php if ($question->isTipEnabled()) { ?>
                            <div class="wpTrivia_tipp" style="display: none; position: relative;">
                                <div>
                                    <h5 style="margin: 0 0 10px;" class="wpTrivia_header"><?php _e('Hint',
                                            'wp-trivia'); ?></h5>
                                    <?php echo do_shortcode(apply_filters('comment_text', $question->getTipMsg())); ?>
                                </div>
                            </div>
                        <?php } ?>

                        <?php if (!$this->quiz->isSkipQuestionDisabled() && $this->quiz->isShowReviewQuestion()) { ?>
                            <input type="button" name="skip" value="<?php _e('Skip question', 'wp-trivia'); ?>"
                                   class="wpTrivia_button wpTrivia_QuestionButton"
                                   style="float: left; margin-right: 10px !important;">
                        <?php } ?>
                        <input type="button" name="back" value="<?php _e('Back', 'wp-trivia'); ?>"
                               class="wpTrivia_button wpTrivia_QuestionButton"
                               style="float: left !important; margin-right: 10px !important; display: none;">
                        <?php if ($question->isTipEnabled()) { ?>
                            <input type="button" name="tip" value="<?php _e('Hint', 'wp-trivia'); ?>"
                                   class="wpTrivia_button wpTrivia_QuestionButton wpTrivia_TipButton"
                                   style="float: left !important; display: inline-block; margin-right: 10px !important;">
                        <?php } ?>
                        <input type="button" name="check" value="<?php _e('Check', 'wp-trivia'); ?>"
                               class="wpTrivia_button wpTrivia_QuestionButton"
                               style="float: right !important; margin-right: 10px !important; display: none;">
                        <input type="button" name="next" value="<?php _e('Next', 'wp-trivia'); ?>"
                               class="wpTrivia_button wpTrivia_QuestionButton" style="float: right; display: none;">

                        <div style="clear: both;"></div>
                    </li>
                <?php } ?>
            </ol>
        </div>
        <?php

        return array('globalPoints' => $globalPoints, 'json' => $json, 'catPoints' => $catPoints);
    }

    private function showLoadQuizBox()
    {
        ?>
        <div style="display: none;" class="wpTrivia_loadQuiz">
            <p>
                <?php echo $this->_buttonNames['quiz_is_loading']; ?>
            </p>
        </div>
        <?php
    }
}
