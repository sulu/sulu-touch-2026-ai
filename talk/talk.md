---
marp: true
theme: sulu
paginate: true
size: 16:9
---

<!-- _class: teal -->

# A Symfony AI Agent on a Sulu page

<div class="subtitle">Sulu:Touch 2026 Workshop</div>

---

<!-- _class: about-me -->

# Hi, I'm Johannes Wachter

- Core Developer at Sulu CMS
- Works with Symfony since 2012
- Open Source & AI Enthusiast
- Father of two

![bg right:40%](assets/portrait.gif)

---

<!-- _class: issues -->

# What is an agent?

- A model that can call your code
- It decides which tool to call
- It stops when it has an answer

---

<!-- _class: issues -->

# Prompt and tools

- The prompt says who the agent is
- A tool is a PHP method with a description
- The description is what the model reads

---

<!-- _class: issues -->

# The tool loop

- Send messages and tool list to the model
- Model answers "call search_products"
- Your code runs it, the result goes back
- Repeat until the model answers in text

---

<!-- _class: issues -->

# From model to agent

- The model has no memory: you send the history
- A bigger prompt is not a better agent
- Fewer, sharper tools win

---

<!-- _class: issues -->

# What a platform adds

- History and runs live on the server
- Every run is visible
- Your tools still run in your app

---

<!-- _class: live-demo -->

# LIVE CODING

## 1. The weather agent in the console

---

<!-- _class: live-demo -->

# LIVE CODING

## 2. Plant tools for the agent

---

<!-- _class: live-demo -->

# LIVE CODING

## 3. The chat in the browser

---

<!-- _class: live-demo -->

# LIVE CODING

## 4. Switch to sulu.ai

---

<!-- _class: teal -->

# Questions?

<div class="subtitle">Thanks for listening!</div>
