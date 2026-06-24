def t(key):
    lang = session.get('lang', 'en')
    return translations.get(lang, {}).get(key, key)

# ============ CURRENCY CONFIGURATION ============
EXCHANGE_RATES = {
    'USD': 1.0,
    'EUR': 0.92,
    'SEK': 10.5,
    'AFN': 71.5,
}

CURRENCY_SYMBOLS = {
    'USD': '$',
    'EUR': '€',
    'SEK': 'kr',
    'AFN': 'AFN',
}

def convert_currency(amount_usd, target_currency='USD'):
    if not target_currency or target_currency not in EXCHANGE_RATES:
        target_currency = 'USD'
    try:
        return float(amount_usd) * EXCHANGE_RATES[target_currency]
    except (TypeError, ValueError):
        return 0

def format_currency(amount_usd, currency=None):
    if currency is None:
        currency = session.get('currency', 'USD')
    if currency not in CURRENCY_SYMBOLS:
        currency = 'USD'
    converted = convert_currency(amount_usd, currency)
    symbol = CURRENCY_SYMBOLS.get(currency, '$')
    if currency == 'AFN':
        return f"{converted:,.0f} {symbol}"
    elif currency == 'SEK':
        return f"{converted:.2f} {symbol}"
    else:
        return f"{symbol}{converted:.2f}"

@app.context_processor
def utility_processor():
    def now():
        return datetime.now()

    def currency():
        curr = session.get('currency', 'USD')
        symbols = {'USD': '$', 'EUR': '€', 'SEK': 'kr', 'AFN': 'AFN'}
        return symbols.get(curr, '$')

    def convert_price(price):
        price_float = float(price)
        currency_code = session.get('currency', 'USD')
        
        if currency_code == 'EUR':
            return price_float * 0.92
        elif currency_code == 'SEK':
            return price_float * 10.5
        elif currency_code == 'AFN':
            return price_float * 71.5
        else:
            return price_float

    def format_price(price):
        try:
            converted = convert_price(price)
            currency_code = session.get('currency', 'USD')
            
            if currency_code == 'EUR':
                return f"€{converted:.2f}"
            elif currency_code == 'SEK':
                return f"{converted:.2f} kr"
            elif currency_code == 'AFN':
                return f"{converted:,.0f} AFN"
            else:
                return f"${converted:.2f}"
        except:
            return f"${price}"

    return dict(
        t=t,
        now=now,
        currency=currency,
        convert_price=convert_price,
        format_price=format_price,
        format_currency=format_currency
    )

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

@app.route('/change-currency/<currency_code>')
def change_currency(currency_code):
    if currency_code in ['USD', 'EUR', 'SEK', 'AFN']:
        session['currency'] = currency_code
        if currency_code == 'USD':
            session['lang'] = 'en'
        elif currency_code == 'EUR':
            session['lang'] = 'fi'
        elif currency_code == 'SEK':
            session['lang'] = 'sv'
        elif currency_code == 'AFN':
            session['lang'] = 'fa'
    return redirect(request.referrer or url_for('index'))
