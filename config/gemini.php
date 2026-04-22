<?php
/**
 * Google Gemini AI Configuration - Loads from .env file
 */

require_once __DIR__ . '/loadenv.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Gemini API Configuration (from .env)
define('GEMINI_API_KEY', GEMINI_API_KEY);
define('GEMINI_MODEL', GEMINI_MODEL);

/**
 * Make API call to Google Gemini
 */
function generateWithGemini($prompt, $options = []) {
    // Check if API key is set
    if (empty(GEMINI_API_KEY) || GEMINI_API_KEY == 'your_gemini_api_key_here') {
        error_log("Gemini API key not configured. Please add your API key to .env file");
        return "AI features are currently disabled. Please configure the Gemini API key in the .env file to enable this feature.";
    }
    
    try {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;
        
        $defaultOptions = [
            'temperature' => 0.7,
            'maxOutputTokens' => 2048,
            'topP' => 0.95,
            'topK' => 40
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => (float)$options['temperature'],
                'maxOutputTokens' => (int)$options['maxOutputTokens'],
                'topP' => (float)$options['topP'],
                'topK' => (int)$options['topK']
            ]
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        ($ch);
        
        if ($curlError) {
            error_log("Gemini API CURL Error: " . $curlError);
            return false;
        }
        
        if ($httpCode !== 200) {
            error_log("Gemini API Error: HTTP $httpCode - $response");
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($result['candidates'][0]['content']['parts'][0]['text']);
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Gemini API Exception: " . $e->getMessage());
        return false;
    }
}


/**
 * Generate exam questions using Gemini AI
 * 
 * @param string $subject The subject/topic
 * @param int $num_questions Number of questions to generate
 * @param string $difficulty Difficulty level (easy, medium, hard)
 * @return array|false Array of questions or false on error
 */
function generateExamQuestions($subject, $num_questions = 5, $difficulty = 'medium') {
    $prompt = "Generate {$num_questions} multiple choice questions about {$subject} with {$difficulty} difficulty.
    
    For each question, follow this exact format:
    
    Question: [question text]
    A) [option A]
    B) [option B]
    C) [option C]
    D) [option D]
    Correct Answer: [A/B/C/D]
    Explanation: [brief explanation]
    
    Separate each question with '---' on a new line.
    
    Example:
    Question: What is the capital of France?
    A) London
    B) Berlin
    C) Paris
    D) Madrid
    Correct Answer: C
    Explanation: Paris is the capital and most populous city of France.
    ---
    
    Now generate {$num_questions} {$difficulty} difficulty questions about {$subject}:";
    
    $response = generateWithGemini($prompt, ['temperature' => 0.8, 'maxOutputTokens' => 4096]);
    
    if (!$response) {
        return false;
    }
    
    return parseGeminiQuestions($response);
}

/**
 * Parse Gemini response into structured questions
 */
function parseGeminiQuestions($response) {
    $questions = [];
    $blocks = explode('---', $response);
    
    foreach ($blocks as $block) {
        $block = trim($block);
        if (empty($block)) continue;
        
        $question = [];
        
        // Extract question text
        if (preg_match('/Question:\s*(.+?)(?=\n[A-D]\)|$)/s', $block, $matches)) {
            $question['question_text'] = trim($matches[1]);
        }
        
        // Extract options
        $options = [];
        if (preg_match_all('/[A-D]\)\s*(.+?)(?=\n[A-D]\)|\nCorrect Answer:|$)/s', $block, $matches)) {
            foreach ($matches[1] as $index => $option) {
                $letter = chr(65 + $index);
                $options[$letter] = trim($option);
            }
        }
        
        $question['option_a'] = $options['A'] ?? '';
        $question['option_b'] = $options['B'] ?? '';
        $question['option_c'] = $options['C'] ?? '';
        $question['option_d'] = $options['D'] ?? '';
        
        // Extract correct answer
        if (preg_match('/Correct Answer:\s*([A-D])/i', $block, $matches)) {
            $question['correct_option'] = strtoupper($matches[1]);
        }
        
        // Extract explanation
        if (preg_match('/Explanation:\s*(.+?)(?=$|---)/s', $block, $matches)) {
            $question['explanation'] = trim($matches[1]);
        }
        
        if (!empty($question['question_text']) && !empty($question['correct_option'])) {
            $questions[] = $question;
        }
    }
    
    return $questions;
}

/**
 * Generate exam description using Gemini AI
 */
function generateExamDescription($title, $subject, $difficulty = 'medium') {
    $prompt = "Write a brief, engaging description (2-3 sentences) for an exam titled '{$title}' on the subject of {$subject} with {$difficulty} difficulty. The description should motivate students and explain what they will learn.";
    
    $response = generateWithGemini($prompt, ['temperature' => 0.6, 'maxOutputTokens' => 200]);
    
    return $response ?: "This exam covers key concepts in {$subject} at a {$difficulty} difficulty level. Good luck!";
}

/**
 * Analyze student answer using Gemini AI
 */
function analyzeStudentAnswer($question, $student_answer, $correct_answer) {
    $prompt = "You are an encouraging teacher. Provide brief, positive feedback for the student.
    
    Question: {$question}
    Student's Answer: {$student_answer}
    Correct Answer: {$correct_answer}
    
    Give a short, encouraging response (1-2 sentences) explaining why their answer was correct or incorrect, and how they can improve.";
    
    $response = generateWithGemini($prompt, ['temperature' => 0.5, 'maxOutputTokens' => 150]);
    
    return $response ?: "Keep practicing! The correct answer is {$correct_answer}. You'll get it next time!";
}

/**
 * Generate study material summary using Gemini AI
 */
function generateStudySummary($topic) {
    $prompt = "Create a concise, easy-to-read study summary for the topic: {$topic}.
    
    Include:
    • Key concepts (3-5 main points)
    • Important definitions
    • Study tips
    • Common mistakes to avoid
    
    Format with bullet points for easy reading. Keep it focused and educational.";
    
    $response = generateWithGemini($prompt, ['temperature' => 0.6, 'maxOutputTokens' => 500]);
    
    return $response ?: "Study summary for {$topic} coming soon. Focus on the key concepts and practice regularly.";
}

/**
 * Check if Gemini API is working
 */
function testGeminiAPI() {
    $test_response = generateWithGemini("Say 'Gemini API is working!' in one short sentence.");
    
    if ($test_response && (strpos($test_response, 'working') !== false || strlen($test_response) > 5)) {
        return true;
    }
    
    return false;
}

/**
 * Generate personalized learning recommendations
 */
function generateRecommendations($student_name, $weak_areas = [], $strong_areas = []) {
    $weak_text = !empty($weak_areas) ? implode(', ', $weak_areas) : 'general improvement';
    $strong_text = !empty($strong_areas) ? implode(', ', $strong_areas) : 'your strengths';
    
    $prompt = "You are a learning coach. Give personalized study recommendations for {$student_name}.
    
    Areas to improve: {$weak_text}
    Strong areas: {$strong_text}
    
    Provide 3 specific, actionable tips to help them improve. Be encouraging and specific.";
    
    $response = generateWithGemini($prompt, ['temperature' => 0.7, 'maxOutputTokens' => 300]);
    
    return $response ?: "1. Review your weak areas daily\n2. Practice with sample questions\n3. Take regular breaks during study sessions";
}
?>