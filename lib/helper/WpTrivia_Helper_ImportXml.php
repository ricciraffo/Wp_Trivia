<?php

class WpTrivia_Helper_ImportXml
{
    private $_content = null;
    private $_error = false;

    public function setImportFileUpload($file)
    {
        if (!is_uploaded_file($file['tmp_name'])) {
            $this->setError(__('File was not uploaded', 'wp-trivia'));

            return false;
        }

        $this->_content = trim(file_get_contents($file['tmp_name']));

        return $this->checkCode();
    }

    public function setImportString($str)
    {
        $this->_content = gzuncompress(base64_decode(trim($str)));

        return true;
    }

    private function checkCode()
    {
        $xml = @simplexml_load_string($this->_content);

        if ($xml === false) {
            $this->_error = __('XML could not be loaded.', 'wp-trivia');

            return false;
        }

        return isset($xml->header);
    }

    public function getImportData()
    {
        $xml = @simplexml_load_string($this->_content, 'SimpleXMLElement', LIBXML_NOCDATA);
        $a = array('master' => array(), 'question' => array(), 'forms' => array());
        $i = 0;

        if ($xml === false) {
            $this->_error = __('XML could not be loaded.', 'wp-trivia');

            return false;
        }

        if (isset($xml->data) && isset($xml->data->quiz)) {
            foreach ($xml->data->quiz as $quiz) {
                $quizModel = $this->createQuizModel($quiz);

                if ($quizModel !== null) {
                    $quizModel->setId($i++);

                    $a['master'][] = $quizModel;

                    if ($quiz->forms->form) {
                        foreach ($quiz->forms->form as $form) {
                            $a['forms'][$quizModel->getId()][] = $this->createFormModel($form);
                        }
                    }

                    if (isset($quiz->questions)) {
                        foreach ($quiz->questions->question as $question) {
                            $questionModel = $this->createQuestionModel($question);

                            if ($questionModel !== null) {
                                $a['question'][$quizModel->getId()][] = $questionModel;
                            }
                        }
                    }
                }
            }
        }

        return $a;
    }

    public function getContent()
    {
        return base64_encode(gzcompress($this->_content));
    }

    public function saveImport($ids)
    {
        $quizMapper = new WpTrivia_Model_QuizMapper();
        $questionMapper = new WpTrivia_Model_QuestionMapper();
        $formMapper = new WpTrivia_Model_FormMapper();

        $data = $this->getImportData();

        foreach ($data['master'] as $quiz) {
            /** @var WpTrivia_Model_Quiz $quiz */

            if (get_class($quiz) !== 'WpTrivia_Model_Quiz') {
                continue;
            }

            $oldId = $quiz->getId();

            if ($ids !== false && !in_array($oldId, $ids)) {
                continue;
            }

            $quiz->setId(0);

            $quizMapper->save($quiz);

            if (isset($data['forms']) && isset($data['forms'][$oldId])) {
                $sort = 0;

                foreach ($data['forms'][$oldId] as $form) {
                    $form->setQuizId($quiz->getId());
                    $form->setSort($sort++);
                }

                $formMapper->update($data['forms'][$oldId]);
            }

            $sort = 0;

            foreach ($data['question'][$oldId] as $question) {
                /** @var WpTrivia_Model_Question $question */

                if (get_class($question) !== 'WpTrivia_Model_Question') {
                    continue;
                }

                $question->setQuizId($quiz->getId());
                $question->setId(0);
                $question->setSort($sort++);

                $questionMapper->save($question);
            }
        }

        return true;
    }

    public function getError()
    {
        return $this->_error;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return WpTrivia_Model_Form
     */
    private function createFormModel($xml)
    {
        $form = new WpTrivia_Model_Form();

        $attr = $xml->attributes();

        if ($attr !== null) {
            $form->setType($attr->type);
            $form->setRequired($attr->required == 'true');
            $form->setFieldname($attr->fieldname);
        }

        if (isset($xml->formData)) {
            $d = array();

            foreach ($xml->formData as $data) {
                $v = trim((string)$data);

                if ($v !== '') {
                    $d[] = $v;
                }
            }

            $form->setData($d);
        }

        return $form;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return null|WpTrivia_Model_Quiz
     */
    private function createQuizModel($xml)
    {
        $model = new WpTrivia_Model_Quiz();

        $model->setName(trim($xml->title));
        $model->setText(trim($xml->text));

        $model->setTimeLimit($xml->timeLimit);

        $model->setFinalText($xml->finalText);

        if (isset($xml->finalText)) {
            $attr = $xml->finalText->attributes();

            if ($attr !== null) {
                $model->setFinalText(trim((string)$xml->finalText));
            }
        }

        $model->setShowPoints($xml->showPoints == 'true');
        $model->setNumberedAnswer($xml->numberedAnswer == 'true');
        $model->setHideAnswerMessageBox($xml->hideAnswerMessageBox == 'true');
        $model->setDisabledAnswerMark($xml->disabledAnswerMark == 'true');

        if (isset($xml->statistic)) {
            $attr = $xml->statistic->attributes();

            if ($attr !== null) {
                $model->setStatisticsOn($attr->activated == 'true');
                $model->setStatisticsIpLock($attr->ipLock);
            }
        }

        if (isset($xml->quizRunOnce)) {
            $model->setQuizRunOnce($xml->quizRunOnce == 'true');
            $attr = $xml->quizRunOnce->attributes();

            if ($attr !== null) {
                $model->setQuizRunOnceCookie($attr->cookie == 'true');
                $model->setQuizRunOnceType($attr->type);
                $model->setQuizRunOnceTime($attr->time);
            }
        }

        if (isset($xml->showMaxQuestion)) {
            $model->setShowMaxQuestion($xml->showMaxQuestion == 'true');
            $attr = $xml->showMaxQuestion->attributes();

            if ($attr !== null) {
                $model->setShowMaxQuestionValue($attr->showMaxQuestionValue);
                $model->setShowMaxQuestionPercent($attr->showMaxQuestionPercent == 'true');
            }
        }

        if (isset($xml->toplist)) {
            $model->setToplistActivated($xml->toplist->attributes()->activated == 'true');

            $model->setToplistDataAddPermissions($xml->toplist->toplistDataAddPermissions);
            $model->setToplistDataSort($xml->toplist->toplistDataSort);
            $model->setToplistDataAddMultiple($xml->toplist->toplistDataAddMultiple == 'true');
            $model->setToplistDataAddBlock($xml->toplist->toplistDataAddBlock);
            $model->setToplistDataShowLimit($xml->toplist->toplistDataShowLimit);
            $model->setToplistDataShowIn($xml->toplist->toplistDataShowIn);
            $model->setToplistDataCaptcha($xml->toplist->toplistDataCaptcha == 'true');
            $model->setToplistDataAddAutomatic($xml->toplist->toplistDataAddAutomatic == 'true');
        }

        $model->setPrerequisite($xml->prerequisite == 'true');
        $model->setSkipQuestionDisabled($xml->skipQuestionDisabled == 'true');
        $model->setEmailNotification($xml->emailNotification);
        $model->setUserEmailNotification($xml->userEmailNotification == 'true');
        $model->setForcingQuestionSolve($xml->forcingQuestionSolve == 'true');
        $model->setHideQuestionPositionOverview($xml->hideQuestionPositionOverview == 'true');
        $model->setHideQuestionNumbering($xml->hideQuestionNumbering == 'true');

        //0.27
        $model->setStartOnlyRegisteredUser($xml->startOnlyRegisteredUser == 'true');

        if (isset($xml->forms)) {
            $attr = $xml->forms->attributes();
            $model->setFormActivated($attr->activated == 'true');
            $model->setFormShowPosition($attr->position);
        }

        //0.29
        if (isset($xml->adminEmail)) {
            $adminEmail = new WpTrivia_Model_Email();
            $adminEmail->setTo($xml->adminEmail->to);
            $adminEmail->setFrom($xml->adminEmail->form);
            $adminEmail->setSubject($xml->adminEmail->subject);
            $adminEmail->setHtml($xml->adminEmail->html == 'true');
            $adminEmail->setMessage($xml->adminEmail->message);

            $model->setAdminEmail($adminEmail);
        }

        if (isset($xml->userEmail)) {
            $userEmail = new WpTrivia_Model_Email();
            $userEmail->setTo($xml->userEmail->to);
            $userEmail->setToUser($xml->userEmail->toUser == 'true');
            $userEmail->setToForm($xml->userEmail->toForm == 'true');
            $userEmail->setFrom($xml->userEmail->form);
            $userEmail->setSubject($xml->userEmail->subject);
            $userEmail->setHtml($xml->userEmail->html == 'true');
            $userEmail->setMessage($xml->userEmail->message);

            $model->setUserEmail($userEmail);
        }

        //Check
        if ($model->getName() == '') {
            return null;
        }

        if ($model->getText() == '') {
            return null;
        }

        return $model;
    }

    /**
     *
     * @param DOMDocument $xml
     * @return NULL|WpTrivia_Model_Question
     */
    private function createQuestionModel($xml)
    {
        $model = new WpTrivia_Model_Question();

        $model->setTitle(trim($xml->title));
        $model->setQuestion(trim($xml->questionText));
        $model->setCorrectMsg(trim($xml->correctMsg));
        $model->setIncorrectMsg(trim($xml->incorrectMsg));
        $model->setAnswerType(trim($xml->attributes()->answerType));
        $model->setCorrectSameText($xml->correctSameText == 'true');

        $model->setTipMsg(trim($xml->tipMsg));

        if (isset($xml->tipMsg) && $xml->tipMsg->attributes() !== null) {
            $model->setTipEnabled($xml->tipMsg->attributes()->enabled == 'true');
        }

        $model->setPoints($xml->points);
        $model->setShowPointsInBox($xml->showPointsInBox == 'true');
        $model->setAnswerPointsActivated($xml->answerPointsActivated == 'true');
        $model->setAnswerPointsDiffModusActivated($xml->answerPointsDiffModusActivated == 'true');
        $model->setDisableCorrect($xml->disableCorrect == 'true');

        $answerData = array();

        if (isset($xml->answers)) {
            foreach ($xml->answers->answer as $answer) {
                $answerModel = new WpTrivia_Model_AnswerTypes();

                $attr = $answer->attributes();

                if ($attr !== null) {
                    $answerModel->setCorrect($attr->correct == 'true');
                    $answerModel->setPoints($attr->points);
                }

                $answerModel->setAnswer(trim($answer->answerText));

                if ($answer->answerText->attributes() !== null) {
                    $answerModel->setHtml($answer->answerText->attributes()->html);
                }

                $answerModel->setSortString(trim($answer->stortText));

                if ($answer->stortText->attributes() !== null) {
                    $answerModel->setSortStringHtml($answer->stortText->attributes()->html);
                }

                $answerData[] = $answerModel;
            }
        }

        $model->setAnswerData($answerData);

        //Check
        if (trim($model->getAnswerType()) == '') {
            return null;
        }

        if (trim($model->getQuestion()) == '') {
            return null;
        }

        if (trim($model->getTitle()) == '') {
            return null;
        }

        if (count($model->getAnswerData()) == 0) {
            return null;
        }

        return $model;
    }
}
