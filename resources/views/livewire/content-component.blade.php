<x-noerd::page :disableModal="$disableModal">

    <x-slot:header>
            <x-noerd::modal-title>Texte</x-noerd::modal-title>
    </x-slot:header>

    <x-client-invoice::order-confirmation-action-bar></x-client-invoice::order-confirmation-action-bar>

    <div x-show="currentTab === 1">
        <div class="bg-white rounded py-0 pb-8">
            <x-noerd::input-label class="pb-2" value="Der Text auf der Startseite, oberhalb der Speisekarte"/>
            <x-noerd::forms.quill :field="'content.start'" :content="$content['start'] ?? ''"/>
        </div>

        <div class="bg-white mt-2 rounded py-0 pb-8">
            <x-noerd::input-label class="pb-2" value="Dieser Text erscheint unterhalb der Speisekarte"/>
            <x-noerd::forms.quill :field="'content.bottom_text'" :content="$content['bottom_text'] ?? ''"/>
        </div>

        <div class="bg-white mt-2 rounded py-0 pb-8">
            <x-noerd::input-label class="pb-2" value="Dieser Text erscheint im Checkout, nach einer Bestellung"/>
            <x-noerd::forms.quill :field="'content.checkout'" :content="$content['checkout'] ?? ''"/>
        </div>
    </div>

    <div x-show="currentTab === 2">
        <div class="bg-white mt-2 rounded py-0 pb-8">
            <x-noerd::input-label class="pb-2" value="Ein Ansprechpartner als Impressum ist gesetzlich verpflichtend."/>
            <x-noerd::forms.quill :field="'content.imprint'" :content="$content['imprint'] ?? ''"/>
        </div>
    </div>

    <div x-show="currentTab === 3">
        <div class="bg-white mt-2 rounded py-0 pb-8">
            <x-noerd::input-label class="pb-2" value="Hier kannst Du den Text für Deine Datenschutzerklärung anpassen."/>
            <x-noerd::forms.quill :field="'content.privacy'" :content="$content['privacy'] ?? ''"/>
        </div>
    </div>

    <div x-show="currentTab === 4">
        <div class="bg-white mt-2 rounded py-0 pb-8">
            <x-noerd::input-label class="pb-2" value="AGB"/>
            <x-noerd::forms.quill :field="'content.agb'" :content="$content['agb'] ?? ''"/>
        </div>
    </div>

    <div x-show="currentTab === 5">
        <div class="bg-white mt-2 rounded py-0 pb-8">
            <x-noerd::input-label class="pb-2" value="Versand"/>
            <x-noerd::forms.quill :field="'content.versand'" :content="$content['versand'] ?? ''"/>
        </div>
    </div>

    <x-slot:footer>
        <x-noerd::delete-save-bar/>
    </x-slot:footer>

</x-noerd::page>
