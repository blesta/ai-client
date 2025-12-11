<?php

/**
 * Example: Ticket Response with Confidence Check
 *
 * This example demonstrates the two-step process for evaluating support tickets:
 * 1. Generate an AI response to a customer support ticket
 * 2. Evaluate the confidence level of that response
 * 3. Decide whether to auto-send or flag for human review
 *
 * This is ideal for implementing automatic AI ticket replies in Blesta's
 * Support Manager with a configurable confidence threshold.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use BlestaAi\Client\BlestaAiClient;
use BlestaAi\Client\Exceptions\AuthenticationException;
use BlestaAi\Client\Exceptions\InsufficientCreditsException;
use BlestaAi\Client\Exceptions\BlestaAiException;

// Replace with your actual API key
$apiKey = 'sk_your_api_key_here';

// For development/testing, you can use localhost
// $client = new BlestaAiClient($apiKey, 'http://localhost:3030/api/v1');

// For production
$client = new BlestaAiClient($apiKey);

// Configuration
$autoReplyThreshold = 90; // Confidence threshold for auto-sending (0-100)

// Sample ticket data
$ticket = [
    'customer_name' => 'John Doe',
    'customer_email' => 'john@example.com',
    'subject' => 'How do I reset my client password?',
    'content' => "Hi, I forgot my password and can't log into my client area. How can I reset it? I've tried the forgot password link but didn't receive an email.",
    'account_type' => 'Basic Support',
    'ticket_id' => '#12345'
];

try {
    echo "=== Support Ticket Analysis ===\n\n";
    echo "Ticket: {$ticket['ticket_id']}\n";
    echo "Customer: {$ticket['customer_name']} ({$ticket['customer_email']})\n";
    echo "Subject: {$ticket['subject']}\n";
    echo "Content: {$ticket['content']}\n\n";

    echo str_repeat('-', 80) . "\n\n";

    // ========================================
    // STEP 1: Generate AI Response
    // ========================================
    echo "STEP 1: Generating AI response to ticket...\n\n";

    $responseGeneration = $client->chatCompletion('openai/gpt-4o', [
        [
            'role' => 'system',
            'content' => 'You are a support agent for Blesta, a client management and billing platform. Provide helpful, accurate, and professional responses to customer support tickets. Be concise but thorough. If you need to reference documentation, use https://docs.blesta.com/.'
        ],
        [
            'role' => 'user',
            'content' => "Customer: {$ticket['customer_name']}\nEmail: {$ticket['customer_email']}\nAccount Type: {$ticket['account_type']}\n\nTicket:\nSubject: {$ticket['subject']}\n\n{$ticket['content']}"
        ]
    ], [
        'temperature' => 0.7,
        'max_tokens' => 500
    ]);

    $proposedReply = $responseGeneration->getContent();

    echo "AI Response:\n";
    echo str_repeat('-', 80) . "\n";
    echo $proposedReply . "\n";
    echo str_repeat('-', 80) . "\n\n";

    echo "Generation Cost: $" . number_format($responseGeneration->usage->cost, 6) . "\n";
    echo "Tokens Used: {$responseGeneration->usage->totalTokens}\n\n";

    echo str_repeat('-', 80) . "\n\n";

    // ========================================
    // STEP 2: Evaluate Confidence
    // ========================================
    echo "STEP 2: Evaluating response confidence...\n\n";

    $confidenceEvaluation = $client->chatCompletion('openai/gpt-4o-mini', [
        [
            'role' => 'system',
            'content' => 'You are a quality assurance evaluator for customer support responses. Analyze the proposed response and rate its confidence level (0-100) based on:

1. **Completeness**: Does it fully address all aspects of the question?
2. **Accuracy**: Is the information correct and specific to Blesta?
3. **Clarity**: Is the response clear and unambiguous?
4. **Actionability**: Can the customer act on this information?
5. **Risk**: Could this response cause issues if incorrect?

Confidence scoring guidelines:
- 90-100: Highly confident - Straightforward question with definitive answer
- 70-89: Moderately confident - Answer is likely correct but may need verification
- 50-69: Low confidence - Uncertain or incomplete information
- 0-49: Very low confidence - Cannot provide reliable answer

Respond in JSON format:
{
    "confidence": 0-100,
    "reasoning": "detailed explanation of confidence level",
    "concerns": ["list of any concerns or caveats"],
    "recommendation": "auto_send" or "human_review",
    "strengths": ["what the response does well"],
    "improvements": ["optional suggestions if any"]
}'
        ],
        [
            'role' => 'user',
            'content' => "Original Ticket:\nSubject: {$ticket['subject']}\n\n{$ticket['content']}\n\nProposed Response:\n{$proposedReply}\n\nEvaluate this response and provide confidence assessment."
        ]
    ], [
        'temperature' => 0.3, // Lower temperature for more consistent evaluation
        'max_tokens' => 400,
        'response_format' => ['type' => 'json_object']
    ]);

    $evaluationJson = $confidenceEvaluation->getContent();
    $evaluation = json_decode($evaluationJson, true);

    if (!$evaluation) {
        throw new Exception("Failed to parse confidence evaluation JSON");
    }

    echo "Confidence Evaluation:\n";
    echo str_repeat('-', 80) . "\n";
    echo "Confidence Score: {$evaluation['confidence']}%\n";
    echo "Recommendation: " . strtoupper($evaluation['recommendation']) . "\n\n";

    echo "Reasoning:\n{$evaluation['reasoning']}\n\n";

    if (!empty($evaluation['strengths'])) {
        echo "Strengths:\n";
        foreach ($evaluation['strengths'] as $strength) {
            echo "  ✓ {$strength}\n";
        }
        echo "\n";
    }

    if (!empty($evaluation['concerns'])) {
        echo "Concerns:\n";
        foreach ($evaluation['concerns'] as $concern) {
            echo "  ⚠ {$concern}\n";
        }
        echo "\n";
    }

    if (!empty($evaluation['improvements'])) {
        echo "Suggested Improvements:\n";
        foreach ($evaluation['improvements'] as $improvement) {
            echo "  • {$improvement}\n";
        }
        echo "\n";
    }

    echo "Evaluation Cost: $" . number_format($confidenceEvaluation->usage->cost, 6) . "\n";
    echo "Tokens Used: {$confidenceEvaluation->usage->totalTokens}\n";
    echo str_repeat('-', 80) . "\n\n";

    // ========================================
    // STEP 3: Decision Logic
    // ========================================
    echo "STEP 3: Decision based on confidence threshold ({$autoReplyThreshold}%)...\n\n";

    $totalCost = $responseGeneration->usage->cost + $confidenceEvaluation->usage->cost;

    if ($evaluation['confidence'] >= $autoReplyThreshold) {
        echo "✓ DECISION: AUTO-SEND REPLY\n\n";
        echo "The AI response has sufficient confidence ({$evaluation['confidence']}% >= {$autoReplyThreshold}%).\n";
        echo "This response would be automatically posted to the ticket.\n\n";

        // In a real implementation, you would:
        // $ticketSystem->addReply($ticket['ticket_id'], $proposedReply, [
        //     'ai_generated' => true,
        //     'ai_confidence' => $evaluation['confidence'],
        //     'ai_model' => $responseGeneration->model,
        //     'ai_cost' => $totalCost
        // ]);

        echo "Internal Note (would be added to ticket):\n";
        echo "---\n";
        echo "AI Auto-Reply\n";
        echo "Confidence: {$evaluation['confidence']}%\n";
        echo "Model: {$responseGeneration->model}\n";
        echo "Cost: $" . number_format($totalCost, 6) . "\n";
        echo "Reasoning: {$evaluation['reasoning']}\n";
        echo "---\n";

    } else {
        echo "⚠ DECISION: SAVE AS DRAFT FOR HUMAN REVIEW\n\n";
        echo "The AI response has insufficient confidence ({$evaluation['confidence']}% < {$autoReplyThreshold}%).\n";
        echo "This response would be saved as a draft for staff review.\n\n";

        // In a real implementation, you would:
        // $ticketSystem->saveDraftReply($ticket['ticket_id'], $proposedReply, [
        //     'ai_generated' => true,
        //     'ai_confidence' => $evaluation['confidence'],
        //     'ai_concerns' => $evaluation['concerns'],
        //     'requires_review' => true
        // ]);

        echo "Draft Note (would be shown to staff):\n";
        echo "---\n";
        echo "AI Draft Response - Review Required\n";
        echo "Confidence: {$evaluation['confidence']}%\n";
        echo "Threshold: {$autoReplyThreshold}%\n\n";

        if (!empty($evaluation['concerns'])) {
            echo "Concerns:\n";
            foreach ($evaluation['concerns'] as $concern) {
                echo "• {$concern}\n";
            }
            echo "\n";
        }

        echo "Staff should review and edit before sending.\n";
        echo "---\n";
    }

    // ========================================
    // Summary
    // ========================================
    echo "\n" . str_repeat('=', 80) . "\n\n";
    echo "SUMMARY:\n";
    echo "- Total API Calls: 2\n";
    echo "- Total Cost: $" . number_format($totalCost, 6) . "\n";
    echo "- Remaining Balance: $" . number_format($confidenceEvaluation->usage->remainingBalance, 4) . "\n";
    echo "- Confidence: {$evaluation['confidence']}%\n";
    echo "- Action: " . ($evaluation['confidence'] >= $autoReplyThreshold ? 'Auto-sent' : 'Draft for review') . "\n";

} catch (AuthenticationException $e) {
    echo "Authentication failed: {$e->getMessage()}\n";
    echo "Please check your API key.\n";
} catch (InsufficientCreditsException $e) {
    echo "Insufficient credits: {$e->getMessage()}\n";
    echo "Required: $" . number_format($e->required, 4) . "\n";
    echo "Available: $" . number_format($e->available, 4) . "\n";
} catch (BlestaAiException $e) {
    echo "API error: {$e->getMessage()}\n";
    echo "Code: {$e->getCode()}\n";
} catch (Exception $e) {
    echo "Unexpected error: {$e->getMessage()}\n";
}
