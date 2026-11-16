<?php

declare(strict_types=1);

namespace Noerd\Cms\Forms;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Noerd\Cms\Exceptions\FormSubmissionException;
use Noerd\Cms\Jobs\SendFormConfirmationEmail;
use Noerd\Cms\Jobs\SendFormNotificationEmail;
use Noerd\Cms\Models\FormRequest;
use Noerd\Cms\Models\FormType;
use Noerd\Support\TenantContext;

/**
 * The ONE way a form submission enters the CMS — the token API
 * (`POST /api/cms/form-requests`) and the website's own forms alike.
 *
 * It is FAIL-CLOSED: the form type must be active and its form YAML must be
 * readable and declare fields; otherwise the submission is refused
 * (FormSubmissionException), nothing is stored, no mail goes out. The data is
 * narrowed to the declared fields and validated by their rules
 * (FormDefinition::rules(), ValidationException on failure). The mails are
 * queued after the commit: the notification when the type sends one, the
 * confirmation when it confirms to the visitor.
 */
final class FormSubmissionService
{
    /**
     * Upper bound for the JSON-encoded submission, so a sender cannot grow
     * `cms_form_requests` rows without limit.
     */
    public const MAX_PAYLOAD_BYTES = 65535;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws FormSubmissionException
     * @throws ValidationException
     */
    public function submit(int $tenantId, string $formKey, array $data): FormRequest
    {
        return TenantContext::run($tenantId, fn(): FormRequest => $this->store($tenantId, $formKey, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function store(int $tenantId, string $formKey, array $data): FormRequest
    {
        $formType = FormType::query()
            ->ofTenant($tenantId)
            ->active()
            ->where('key', $formKey)
            ->first();

        if ($formType === null) {
            throw FormSubmissionException::unknownForm();
        }

        // json_encode() escapes every non-ASCII character, so its length is
        // the byte length the column limits count.
        if (mb_strlen((string) json_encode($data)) > self::MAX_PAYLOAD_BYTES) {
            throw FormSubmissionException::payloadTooLarge();
        }

        $definition = $formType->definition();

        if ($definition === null || $definition->fields() === []) {
            logger()->warning('Form submission refused: the form definition is missing', [
                'tenant_id' => $tenantId,
                'form' => $formKey,
                'yml_path' => $formType->yml_path,
            ]);

            throw FormSubmissionException::missingDefinition();
        }

        $data = array_intersect_key($data, array_flip($definition->fieldNames()));
        [$rules, $messages] = $definition->rules();
        Validator::make($data, $rules, $messages)->validate();

        $formRequest = FormRequest::create([
            'form' => $formKey,
            'form_type_id' => $formType->id,
            'tenant_id' => $tenantId,
            'data' => $data,
        ]);

        if ($formType->send_email) {
            SendFormNotificationEmail::dispatch($formRequest)->afterCommit();
        }

        if ($formType->send_confirmation) {
            SendFormConfirmationEmail::dispatch($formRequest)->afterCommit();
        }

        return $formRequest;
    }
}
