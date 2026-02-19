# Page snapshot

```yaml
- generic [ref=e2]:
  - generic [ref=e3]:
    - heading "StudySprint" [level=1] [ref=e4]
    - paragraph [ref=e5]: Connectez-vous pour continuer
  - generic [ref=e6]:
    - generic [ref=e7]:
      - generic [ref=e8]: Email
      - textbox "Email" [active] [ref=e9]:
        - /placeholder: votre@email.com
    - generic [ref=e10]:
      - generic [ref=e11]: Mot de passe
      - textbox "Mot de passe" [ref=e12]:
        - /placeholder: ••••••••
    - button "Se connecter" [ref=e13] [cursor=pointer]
  - paragraph [ref=e14]:
    - text: Pas encore de compte ?
    - link "S'inscrire" [ref=e15] [cursor=pointer]:
      - /url: /register
```