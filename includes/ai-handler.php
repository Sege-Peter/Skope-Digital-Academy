<?php
/**
 * 🤖 SDAC AI Engine – Google Gemini 1.5 Flash Integration
 * Orchestrates all AI logic for Students, Tutors, and Admins.
 */

require_once __DIR__ . '/db.php';

class SDAC_AI {
    /**
     * General Query – Direct prompt to Gemini 1.5 Flash
     */
    public static function ask($prompt, $system_instruction = "You are the official SDAC AI Mentor. You are professional, encouraging, and highly knowledgeable about the academy's curriculum and the tech ecosystem.") {
        if (empty($prompt)) return "I require a prompt to assist you.";
        
        $payload = [
            "contents" => [
                [
                    "role" => "user",
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => 0.7,
                "topK" => 40,
                "topP" => 0.95,
                "maxOutputTokens" => 2048,
            ]
        ];

        // Support for official systemInstruction field
        if (!empty($system_instruction)) {
            $payload["systemInstruction"] = [
                "parts" => [
                    ["text" => $system_instruction]
                ]
            ];
        }

        return self::execute($payload);
    }

    /**
     * 👩‍🎓 Student: Study Buddy
     */
    public static function studyBuddy($lesson_title, $course_desc, $student_query) {
        $system = "You are SDAC AI, the primary Study Buddy for Skope Digital Academy.
                   Context: Course '$course_desc', Lesson '$lesson_title'.
                   Help the student master the content with clear explanations and professional encouragement.";
        return self::ask($student_query, $system);
    }

    /**
     * 👨‍🏫 Tutor: Quiz Generator
     */
    public static function generateQuiz($topic, $num_questions = 5) {
        $system = "You are the SDAC Exam Architect. Generate $num_questions MCQs for topic: '$topic'.
                   Return STRICTLY valid JSON like: [{\"question\":\"?\",\"options\":[],\"correct\":\"\"}].
                   Return JSON ONLY. No backticks.";
        
        $response = self::ask("Generate the quiz now.", $system);
        $json = preg_replace('/^```json\s*|```$/', '', trim($response));
        return json_decode($json, true);
    }

    /**
     * 👨‍🏫 Tutor: Lesson Generator
     */
    public static function generateLesson($topic) {
        $system = "You are the SDAC Curriculum Designer. Generate a detailed lesson structure for: '$topic'.
                   Return STRICTLY valid JSON like: {\"title\":\"?\",\"content\":\"?\",\"duration\":15}.
                   Content should be at least 300 words with educational structure.
                   Return JSON ONLY. No backticks.";
        
        $response = self::ask("Generate the lesson now.", $system);
        $json = preg_replace('/^```json\s*|```$/', '', trim($response));
        return json_decode($json, true);
    }

    /**
     * 🛡️ Admin: Strategic Revenue Analysis
     */
    public static function revenueInsight($revenue_data) {
        $system = "You are the SDAC Chief Financial Analyst. Analyze the following revenue registry and provide 3 executive-level strategic growth insights: " . json_encode($revenue_data);
        return self::ask("Analyze the fiscal data.", $system);
    }

    /**
     * 🏛️ Transcript: Provost Commendation
     */
    public static function academicCommendation($metrics) {
        $system = "You are the SDAC Provost. Write a formal 50-word commendation for a student with these metrics: " . json_encode($metrics);
        return self::ask("Write the official commendation.", $system);
    }

    /**
     * Build the network request
     */
    private static function execute($payload) {
        $ch = curl_init(GEMINI_API_URL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        
        if ($http_code !== 200) {
            $msg = $data['error']['message'] ?? "Institutional Registry Offline (Code $http_code)";
            return "SDAC Registry Error: $msg";
        }

        // Handle Safety Blocks
        if (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] === 'SAFETY') {
            return "Institutional Protocol: Content blocked for safety compliance.";
        }

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? "Intelligence stream capped. Please refine your query.";
    }
}
