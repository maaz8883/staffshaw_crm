{{-- Editor "+" insert menu (Trello-style), shared by the description and comment editors --}}
<div class="tc-ins-search">
    <i class="bi bi-search"></i>
    <input type="text" class="tc-ins-q" placeholder="Search" autocomplete="off" aria-label="Search insert options">
    <span class="tc-ins-enter"><i class="bi bi-arrow-return-left"></i> Enter</span>
</div>
<button type="button" class="tc-dd-item tc-ins-item" data-insert="mention" data-keys="mention @ notify user">
    <span class="ico">@</span>
    <span class="txt"><strong>Mention</strong><span class="sub">Mention someone to send them a notification</span></span>
    <kbd>@</kbd>
</button>
<button type="button" class="tc-dd-item tc-ins-item" data-insert="emoji" data-keys="emoji : smile">
    <span class="ico"><i class="bi bi-emoji-smile"></i></span>
    <span class="txt"><strong>Emoji</strong><span class="sub">Use emojis to express ideas 🎉 and emotions 😄</span></span>
    <kbd>:</kbd>
</button>
<button type="button" class="tc-dd-item tc-ins-item" data-insert="code" data-keys="code snippet ```">
    <span class="ico"><i class="bi bi-code-slash"></i></span>
    <span class="txt"><strong>Code snippet</strong><span class="sub">Display code with syntax highlighting</span></span>
    <kbd>```</kbd>
</button>
<button type="button" class="tc-dd-item tc-ins-item" data-insert="quote" data-keys="quote citation >">
    <span class="ico"><i class="bi bi-quote"></i></span>
    <span class="txt"><strong>Quote</strong><span class="sub">Insert a quote or citation</span></span>
    <kbd>&gt;</kbd>
</button>
<div class="tc-ins-empty tc-muted" hidden>No results</div>
