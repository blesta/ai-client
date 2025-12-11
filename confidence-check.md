# AI Response Confidence Check for Blesta Integration

## Overview

When implementing automatic AI ticket replies in Blesta's Support Manager, determining response confidence is critical. This document outlines recommended approaches for assessing whether an AI-generated response is reliable enough to send automatically.

## Recommended Approaches

### Option 1: Single-Step Meta-Prompting (Simpler)

Ask the AI to include a confidence score directly in its response.

**Pros:**
- Single API call (lower cost, faster)
- Simple implementation
- Real-time confidence assessment

**Cons:**
- Less reliable (AI evaluating itself in same context)
- Requires response parsing
- May be influenced by model's overconfidence

**Implementation:**

```php
$messages = [
    [
        'role' => 'system',
        'content' => 'You are a support agent for Blesta. When responding to tickets, you must assess your confidence level and include it in your response. Format your response as:

CONFIDENCE: [0-100]
REASONING: [Brief explanation of your confidence level]
RESPONSE: [Your actual support response]

Guidelines for confidence scoring:
- 90-100: Highly confident - Question is straightforward with definitive answer
- 70-89: Moderately confident - Answer is likely correct but may need verification
- 50-69: Low confidence - Uncertain or incomplete information
- 0-49: Very low confidence - Cannot provide reliable answer'
    ],
    [
        'role' => 'user',
        'content' => "Ticket from {$customer_name}:\n\n{$ticket_content}"
    ]
];

// Parse response
preg_match('/CONFIDENCE:\s*(\d+)/', $aiResponse, $confidenceMatch);
preg_match('/REASONING:\s*(.+?)(?=RESPONSE:)/s', $aiResponse, $reasoningMatch);
preg_match('/RESPONSE:\s*(.+)/s', $aiResponse, $responseMatch);

$confidence = (int)($confidenceMatch[1] ?? 0);
$reasoning = trim($reasoningMatch[1] ?? '');
$response = trim($responseMatch[1] ?? '');
```

---

### Option 2: Two-Step Process (More Reliable) ⭐ **RECOMMENDED**

Generate the response first, then have the AI evaluate its own response in a separate call.

**Pros:**
- More objective evaluation (separate context)
- AI can critically analyze the response quality
- Better reasoning about confidence factors
- Can include additional evaluation criteria

**Cons:**
- Two API calls (higher cost, slightly slower)
- More complex implementation

**Implementation:**

**Step 1: Generate the response**

```php
// First call: Generate support response
$messages = [
    [
        'role' => 'system',
        'content' => 'You are a support agent for Blesta, a client management and billing platform. Provide helpful, accurate responses to customer support tickets. Be concise and professional.'
    ],
    [
        'role' => 'user',
        'content' => "Customer: {$customer_name}\nAccount Type: {$account_type}\n\nTicket:\n{$ticket_content}"
    ]
];

$response = $blestaAiClient->chatCompletion($messages, [
    'model' => 'openai/gpt-4o',
    'max_tokens' => 500
]);

$proposedReply = $response['choices'][0]['message']['content'];
```

**Step 2: Evaluate confidence**

```php
// Second call: Evaluate the response confidence
$evaluationMessages = [
    [
        'role' => 'system',
        'content' => 'You are a quality assurance evaluator for customer support responses. Analyze the proposed response and rate its confidence level (0-100) based on:

1. **Completeness**: Does it fully address all aspects of the question?
2. **Accuracy**: Is the information correct and specific to Blesta?
3. **Clarity**: Is the response clear and unambiguous?
4. **Actionability**: Can the customer act on this information?
5. **Risk**: Could this response cause issues if incorrect?

Respond in JSON format:
{
    "confidence": 0-100,
    "reasoning": "explanation",
    "concerns": ["list of any concerns"],
    "recommendation": "auto_send" or "human_review"
}'
    ],
    [
        'role' => 'user',
        'content' => "Original Ticket:\n{$ticket_content}\n\nProposed Response:\n{$proposedReply}\n\nEvaluate this response and provide confidence assessment."
    ]
];

$evaluation = $blestaAiClient->chatCompletion($evaluationMessages, [
    'model' => 'openai/gpt-4o-mini', // Use cheaper model for evaluation
    'max_tokens' => 200,
    'response_format' => ['type' => 'json_object']
]);

$confidenceData = json_decode($evaluation['choices'][0]['message']['content'], true);
$confidence = $confidenceData['confidence'];
$reasoning = $confidenceData['reasoning'];
$concerns = $confidenceData['concerns'] ?? [];
```

**Step 3: Decision logic**

```php
$autoReplyThreshold = $settings['ai_auto_reply_threshold'] ?? 90;

if ($confidence >= $autoReplyThreshold) {
    // Automatically post reply
    $this->addTicketReply($ticketId, $proposedReply, [
        'staff_id' => null, // AI-generated
        'type' => 'reply',
        'ai_generated' => true,
        'ai_confidence' => $confidence,
        'ai_reasoning' => $reasoning
    ]);

    // Add internal note about AI response
    $this->addTicketNote($ticketId,
        "AI Auto-Reply (Confidence: {$confidence}%)\nReasoning: {$reasoning}",
        ['staff_id' => null, 'ai_metadata' => true]
    );

} else {
    // Save as draft for staff review
    $this->saveDraftReply($ticketId, $proposedReply, [
        'ai_generated' => true,
        'ai_confidence' => $confidence,
        'ai_reasoning' => $reasoning,
        'ai_concerns' => $concerns,
        'requires_review' => true
    ]);

    // Optionally notify staff
    if (!empty($concerns)) {
        $this->notifyStaff($ticketId,
            "AI response requires review (Confidence: {$confidence}%)\nConcerns: " .
            implode(', ', $concerns)
        );
    }
}
```

---

## Configuration Settings

### Support Manager Settings UI

```php
// In Blesta admin settings form
$fields = [
    'ai_auto_reply_enabled' => [
        'type' => 'checkbox',
        'label' => 'Enable Automatic AI Replies',
        'default' => false
    ],
    'ai_auto_reply_threshold' => [
        'type' => 'range',
        'label' => 'Confidence Threshold for Auto-Reply',
        'min' => 50,
        'max' => 100,
        'step' => 5,
        'default' => 90,
        'tooltip' => 'AI responses with confidence at or above this level will be sent automatically. Lower confidence responses will be saved as drafts for review.'
    ],
    'ai_evaluation_model' => [
        'type' => 'select',
        'label' => 'Confidence Evaluation Model',
        'options' => [
            'openai/gpt-4o-mini' => 'GPT-4o Mini (Recommended)',
            'openai/gpt-4o' => 'GPT-4o (Higher accuracy)',
            'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet'
        ],
        'default' => 'openai/gpt-4o-mini'
    ]
];
```

---

## Cost Considerations

### Single-Step (Option 1)
- **1 API call per ticket**
- Typical cost: ~$0.001 - $0.005 per ticket (depending on model)

### Two-Step (Option 2)
- **2 API calls per ticket**
- Response generation: $0.001 - $0.005 (using GPT-4o)
- Confidence evaluation: $0.0002 - $0.001 (using GPT-4o-mini)
- **Total: ~$0.0012 - $0.006 per ticket**

**Cost optimization tip**: Use a smaller/cheaper model for the evaluation step (e.g., GPT-4o-mini instead of GPT-4o) since confidence evaluation is a simpler task.

---

## Testing Recommendations

### Test Cases for Confidence Scoring

1. **High Confidence Scenarios (90-100%)**
   - Simple factual questions with definitive answers
   - Common questions with clear documentation
   - Password reset requests
   - Billing cycle inquiries

2. **Medium Confidence Scenarios (70-89%)**
   - Questions requiring account-specific details
   - Configuration questions with multiple valid approaches
   - Feature usage questions

3. **Low Confidence Scenarios (50-69%)**
   - Complex technical issues
   - Questions about undocumented features
   - Ambiguous requests
   - Questions requiring access to external systems

4. **Very Low Confidence Scenarios (0-49%)**
   - Requests for account-specific data
   - Questions outside Blesta's scope
   - Unclear or incomplete tickets

### Sample Test Tickets

```php
// High confidence test
$ticket1 = "How do I reset my password?";
// Expected: 95-100% confidence

// Medium confidence test
$ticket2 = "Can I customize the invoice template to include my company logo?";
// Expected: 75-90% confidence

// Low confidence test
$ticket3 = "My payment gateway is returning error code XYZ123 intermittently.";
// Expected: 50-70% confidence

// Very low confidence test
$ticket4 = "What's my current account balance?";
// Expected: 0-30% confidence (requires database lookup)
```

---

## Audit Trail and Transparency

### Database Schema for Tracking

```sql
ALTER TABLE support_replies ADD COLUMN ai_generated TINYINT(1) DEFAULT 0;
ALTER TABLE support_replies ADD COLUMN ai_confidence TINYINT UNSIGNED NULL;
ALTER TABLE support_replies ADD COLUMN ai_model VARCHAR(100) NULL;
ALTER TABLE support_replies ADD COLUMN ai_reasoning TEXT NULL;
ALTER TABLE support_replies ADD COLUMN ai_evaluation_data JSON NULL;
```

### Logging AI Decisions

```php
// Log every AI decision for audit purposes
$this->logAiDecision([
    'ticket_id' => $ticketId,
    'confidence' => $confidence,
    'threshold' => $autoReplyThreshold,
    'action' => $confidence >= $autoReplyThreshold ? 'auto_sent' : 'draft_created',
    'model' => $model,
    'reasoning' => $reasoning,
    'concerns' => $concerns,
    'cost' => $totalCost,
    'timestamp' => time()
]);
```

---

## Recommendation

**Use the Two-Step Process (Option 2)** for production implementation because:

1. **More reliable**: Separate evaluation context reduces bias
2. **Transparent**: Clear reasoning and concerns for staff review
3. **Flexible**: Easy to adjust evaluation criteria
4. **Auditable**: Detailed logging of decision process
5. **Cost-effective**: Use cheaper model for evaluation step

The additional cost (~$0.001 per ticket) is negligible compared to the value of preventing incorrect automatic responses and maintaining customer trust.
