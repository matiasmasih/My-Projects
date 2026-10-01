@app.route('/set-lang/<lang>')
def set_language(lang):
    if lang in ['en', 'fi', 'sv', 'fa']:
        session['lang'] = lang
        if lang == 'en':
            session['currency'] = 'USD'
        elif lang == 'fi':
            session['currency'] = 'EUR'
        elif lang == 'sv':
            session['currency'] = 'SEK'
        elif lang == 'fa':
            session['currency'] = 'AFN'
    return redirect(request.referrer or url_for('index'))
